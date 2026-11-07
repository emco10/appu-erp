"""Fail closed on scan errors and findings without an exact, source-bound review."""
from __future__ import annotations

import argparse
from datetime import date
import hashlib
import json
from pathlib import Path
from pathlib import PurePosixPath
import re
import subprocess
import sys
from urllib.parse import unquote


def load_object(path: Path) -> dict:
    value = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(value, dict):
        raise ValueError(f"Invalid report object: {path.name}")
    return value


def repository_path(value: object) -> str:
    if not isinstance(value, str) or not value or any(char in value for char in "\\:*?[]\x00\r\n"):
        raise ValueError("Review paths must identify one repository file, without wildcards")
    path = PurePosixPath(value)
    if path.is_absolute() or '..' in path.parts or str(path) != value:
        raise ValueError("Review paths must stay inside the repository")
    return value


def scanner_engine(engine_name: str) -> str:
    return 'semgrep' if engine_name.lower().startswith('semgrep') else 'codeql' if engine_name.lower() == 'codeql' else engine_name.lower()


def finding_identity(engine_name: str, result: dict) -> dict:
    """CE's 'requires login' is not a fingerprint: derive a local exact identity."""
    engine = scanner_engine(engine_name)
    physical = (result.get('locations') or [{}])[0].get('physicalLocation', {})
    path = repository_path(unquote(str(physical.get('artifactLocation', {}).get('uri', 'unknown-file'))))
    region = physical.get('region', {})
    native = {}
    for key in ['fingerprints', 'partialFingerprints']:
        for name, value in result.get(key, {}).items():
            if isinstance(value, str) and value and value != 'requires login':
                native[name] = value
    identity = {'engine': engine, 'rule_id': str(result.get('ruleId', 'unknown-rule')), 'path': path,
                'region': {key: region[key] for key in ['startLine', 'startColumn', 'endLine', 'endColumn'] if key in region},
                'level': result.get('level', 'warning'), 'message': result.get('message', {}), 'native_fingerprints': native}
    canonical = json.dumps(identity, sort_keys=True, separators=(',', ':'), ensure_ascii=False).encode('utf-8')
    identity['fingerprint'] = hashlib.sha256(canonical).hexdigest()
    identity['label'] = f"{identity['rule_id']} at {path}:{region.get('startLine', '?')}"
    return identity


class GitSources:
    """Read committed bytes only; a working-tree hash cannot attest a reviewed source."""
    def __init__(self, root: Path, revision: str = 'HEAD'):
        self.root = root.resolve()
        self.cache = {}
        if not revision or revision.startswith('-'):
            raise ValueError('Invalid source revision')
        self.commit = self.git('rev-parse', '--verify', revision + '^{commit}').decode().strip()
        if not re.fullmatch(r'[a-f0-9]{40}|[a-f0-9]{64}', self.commit):
            raise ValueError('Source must resolve to a Git commit')

    def git(self, *args: str) -> bytes:
        result = subprocess.run(['git', '-C', str(self.root), *args], capture_output=True)
        if result.returncode:
            raise ValueError('Cannot verify a committed review source or dependency')
        return result.stdout

    def digest(self, path: str, commit: str | None = None) -> str:
        path = repository_path(path)
        key = (commit or self.commit, path)
        if key not in self.cache:
            self.cache[key] = hashlib.sha256(self.git('show', key[0] + ':' + path)).hexdigest()
        return self.cache[key]


def reviewed_findings(findings: list[dict], manifest: Path, sources: GitSources, analyzed_engines: set[str] | None = None) -> tuple[list[dict], list[dict]]:
    data = load_object(manifest)
    entries = data.get('findings')
    if data.get('schema_version') != 1 or not isinstance(entries, list):
        raise ValueError('Unsupported reviewed-finding manifest')
    engines = analyzed_engines if analyzed_engines is not None else {row['engine'] for row in findings}
    active = {}
    all_ids = set()
    for entry in entries:
        if not isinstance(entry, dict):
            raise ValueError('Invalid reviewed-finding entry')
        for key in ['id','engine','rule_id','reviewer','rationale','dependency_review','scanner_version']:
            if not isinstance(entry.get(key), str) or not entry[key].strip():
                raise ValueError('Reviewed finding is missing explicit review metadata: ' + key)
        if len(entry['rationale']) < 30 or len(entry['dependency_review']) < 10 or entry['disposition'] not in ['false_positive','reviewed_audit','vendor_reviewed']:
            raise ValueError('Reviewed finding needs a specific rationale and disposition')
        if not re.fullmatch(r'\d{4}-\d{2}-\d{2}', str(entry.get('reviewed_on', ''))):
            raise ValueError('Review date must use YYYY-MM-DD')
        date.fromisoformat(entry['reviewed_on'])
        if entry['id'] in all_ids:
            raise ValueError('Duplicate reviewed-finding ID')
        all_ids.add(entry['id'])
        path = repository_path(entry['path'])
        for key in ['fingerprint','source_sha256']:
            if not re.fullmatch(r'[a-f0-9]{64}', str(entry.get(key, ''))):
                raise ValueError('Reviewed finding needs exact SHA-256 fingerprints and source hashes')
        if not re.fullmatch(r'[a-f0-9]{40}|[a-f0-9]{64}', str(entry.get('reviewed_commit', ''))):
            raise ValueError('Reviewed finding must name a Git commit')
        if not isinstance(entry.get('dependencies'), list) or not isinstance(entry.get('evidence'), list) or not entry['evidence']:
            raise ValueError('Dependency review and hash-bound evidence are required')
        # Validate every entry's schema. Only this engine's entries are applied.
        if entry['engine'] not in engines:
            continue
        sources.git('merge-base', '--is-ancestor', entry['reviewed_commit'], sources.commit)
        for item in [{'path': path, 'sha256': entry['source_sha256']}] + entry['dependencies'] + entry['evidence']:
            if not isinstance(item, dict) or not re.fullmatch(r'[a-f0-9]{64}', str(item.get('sha256', ''))):
                raise ValueError('Review dependency/evidence needs a SHA-256 hash')
            source_path = repository_path(item.get('path'))
            if sources.digest(source_path, entry['reviewed_commit']) != item['sha256'] or sources.digest(source_path) != item['sha256']:
                raise ValueError('Reviewed source, critical dependency or evidence changed: ' + source_path)
        for proof in entry['evidence']:
            if not isinstance(proof.get('description'), str) or len(proof['description']) < 15:
                raise ValueError('Review evidence needs an explicit proof description')
        key = (entry['engine'], entry['rule_id'], path, entry['fingerprint'])
        if key in active:
            raise ValueError('Duplicate exact reviewed finding')
        active[key] = entry
    accepted = []
    pending = []
    seen = set()
    for finding in findings:
        key = (finding['engine'], finding['rule_id'], finding['path'], finding['fingerprint'])
        if key in seen:
            raise ValueError('Duplicate scanner identity requires explicit investigation')
        seen.add(key)
        review = active.pop(key, None)
        if review is None:
            pending.append(finding)
        elif review['scanner_version'] != finding['scanner_version']:
            raise ValueError('Scanner version changed; review must be repeated')
        else:
            accepted.append(finding)
    if active:
        raise ValueError('Stale reviewed finding no longer matches the scan; remove or review it')
    return pending, accepted


def evaluate(sarif: Path, semgrep_json: Path | None = None, scanner_exit: Path | None = None,
             allowances: Path | None = None, source_root: Path | None = None, source_commit: str = 'HEAD',
             require_full_codeql: bool = False) -> tuple[list[str], dict]:
    report = load_object(sarif)
    if report.get("version") != "2.1.0" or not isinstance(report.get("runs"), list) or not report["runs"]:
        raise ValueError("Missing SARIF runs or unsupported report format")
    findings = []
    engines = set()
    for run in report["runs"]:
        tool = run.get('tool', {})
        driver = tool.get('driver', {})
        extensions = tool.get('extensions', [])
        if not driver.get('name') or not isinstance(extensions, list):
            raise ValueError("A scan without an identified tool and rules cannot pass")
        # SARIF rule packs can define rules in tool.extensions rather than driver.
        # CodeQL 2.27.4 uses an empty driver.rules with javascript-queries rules here.
        rules = set()
        for component in [driver] + extensions:
            if not isinstance(component, dict) or not isinstance(component.get('rules', []), list):
                raise ValueError('Invalid SARIF rule component')
            for rule in component.get('rules', []):
                if not isinstance(rule, dict) or not isinstance(rule.get('id'), str) or not rule['id']:
                    raise ValueError('SARIF rules require an explicit identity')
                rules.add(rule['id'])
        if not rules:
            raise ValueError('A scan without a rule catalog cannot pass')
        engines.add(scanner_engine(driver['name']))
        if require_full_codeql:
            properties = run.get('properties', {})
            if scanner_engine(driver['name']) != 'codeql':
                raise ValueError('Full CodeQL verification requires a CodeQL report')
            if properties.get('incrementalMode') not in [None, '', 'none', 'full'] or any(
                    component.get('name') == 'codeql-action/pr-diff-range' for component in extensions):
                raise ValueError('Incremental or diff-informed CodeQL cannot satisfy the full-source gate')
            metrics = properties.get('metricResults', [])
            if properties.get('codeqlConfigSummary', {}).get('disableDefaultQueries') is not False or not any(
                    metric.get('ruleId') == 'js/summary/lines-of-user-code'
                    and isinstance(metric.get('value'), (int, float)) and metric['value'] > 0
                    and isinstance(metric.get('baseline'), (int, float)) and metric['baseline'] > 0
                    for metric in metrics):
                raise ValueError('Full CodeQL must attest default queries and nonempty source coverage')
        invocations = run.get('invocations')
        if not isinstance(invocations, list) or not invocations:
            raise ValueError('SARIF must attest a completed scanner invocation')
        for invocation in invocations:
            if invocation.get("executionSuccessful") is not True:
                raise ValueError("Scanner reported an incomplete or failed execution")
            for notification in invocation.get("toolExecutionNotifications", []):
                if notification.get("level") == "error":
                    raise ValueError("Scanner reported an analysis error")
        results = run.get("results")
        if not isinstance(results, list):
            raise ValueError("Missing SARIF results array")
        for result in results:
            if result.get('ruleId') not in rules:
                raise ValueError('SARIF result references an unknown rule')
            # Suppressions, baselines and severity do not implicitly waive a finding.
            finding = finding_identity(driver['name'], result)
            finding['scanner_version'] = str(driver.get('semanticVersion') or driver.get('version') or '')
            findings.append(finding)
    coverage = {}
    if semgrep_json is not None:
        semgrep = load_object(semgrep_json)
        if not isinstance(semgrep.get('errors'), list) or semgrep['errors']:
            raise ValueError("Semgrep reported parsing, rule or analysis errors; review its JSON artifact")
        if engines != {'semgrep'}:
            raise ValueError('The Semgrep JSON must accompany a Semgrep SARIF report')
        paths = semgrep.get("paths", {}).get("scanned")
        if not isinstance(paths, list) or not all(isinstance(path, str) for path in paths):
            raise ValueError("Semgrep did not provide scanned-file coverage")
        coverage = {"php": sum(path.endswith(".php") for path in paths), "javascript": sum(path.endswith(".js") for path in paths)}
        if not all(coverage.values()):
            raise ValueError("Semgrep must actually scan both PHP and JavaScript")
        if not isinstance(semgrep.get('results'), list) or len(semgrep['results']) != sum(row['engine'] == 'semgrep' for row in findings):
            raise ValueError('SARIF and Semgrep JSON findings disagree')
    if scanner_exit is not None:
        status = scanner_exit.read_text(encoding="utf-8").strip()
        if status != "0" and not (status == "1" and findings):
            raise ValueError("Scanner failed or did not complete; review its exit status and artifacts")
    if allowances is not None:
        total = len(findings)
        findings, accepted = reviewed_findings(findings, allowances, GitSources(source_root or Path.cwd(), source_commit), engines)
        coverage['reviewed_findings'] = len(accepted)
        coverage['total_findings'] = total
    return [row['label'] for row in findings], coverage


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--sarif", type=Path, required=True)
    parser.add_argument("--semgrep-json", type=Path)
    parser.add_argument("--scanner-exit", type=Path)
    parser.add_argument('--reviewed-allowances', type=Path)
    parser.add_argument('--source-root', type=Path, default=Path.cwd())
    parser.add_argument('--source-commit', default='HEAD')
    parser.add_argument('--require-full-codeql', action='store_true', help='Reject incremental CodeQL reports and require full-source coverage metrics')
    args = parser.parse_args()
    try:
        findings, coverage = evaluate(args.sarif, args.semgrep_json, args.scanner_exit, args.reviewed_allowances, args.source_root, args.source_commit, args.require_full_codeql)
    except (OSError, ValueError, TypeError, KeyError, AttributeError) as error:
        print(f"FAIL security analysis: {error}", file=sys.stderr)
        return 1
    for finding in findings:
        print("FINDING " + finding)
    accepted = coverage.pop('reviewed_findings', 0)
    total = coverage.pop('total_findings', len(findings))
    print(f"Security analysis: {total} total; {accepted} exact reviews; {len(findings)} unreviewed; scanned coverage: {coverage or 'see SARIF'}")
    return 1 if findings else 0


if __name__ == "__main__":
    sys.exit(main())
