"""Offline security CI gate regressions; no scanner, credentials or network."""
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
import subprocess
import sys

sys.dont_write_bytecode = True

spec = importlib.util.spec_from_file_location("security_gate", Path(__file__).resolve().parents[1] / "bin/check-security-sarif.py")
gate = importlib.util.module_from_spec(spec)
spec.loader.exec_module(gate)


class SecurityGateTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix="appu-security-gate-")
        self.root = Path(self.temporary.name)
        self.report = {"version": "2.1.0", "runs": [{"tool": {"driver": {"name": "Semgrep", "rules": [{"id": "example-security-rule"}, {"id": "sql-injection"}]}}, "invocations": [{"executionSuccessful": True}], "results": []}]}

    def tearDown(self):
        self.temporary.cleanup()

    def evaluate(self, data=None, status="0"):
        sarif = self.root / "report.sarif"
        sarif.write_text(json.dumps(self.report), encoding="utf-8")
        details = self.root / "report.json"
        details.write_text(json.dumps(data if data is not None else {"errors": [], "results": self.report['runs'][0]['results'], "paths": {"scanned": ["global/a.php", "js/a.js"]}}), encoding="utf-8")
        code = self.root / "exit.txt"
        code.write_text(status, encoding="utf-8")
        return gate.evaluate(sarif, details, code)

    def test_complete_zero_finding_scan_passes_with_coverage(self):
        self.assertEqual(self.evaluate(), ([], {"php": 1, "javascript": 1}))

    def test_findings_retained_even_suppressed_note_or_baseline(self):
        self.report["runs"][0]["results"] = [{"ruleId": "sql-injection", "level": "note", "baselineState": "unchanged", "suppressions": [{"status": "accepted"}]}]
        findings, _ = self.evaluate(status="1")
        self.assertEqual(len(findings), 1)
        self.assertIn("sql-injection", findings[0])

    def test_failed_execution_cannot_look_like_empty_scan(self):
        self.report["runs"][0]["invocations"][0]["executionSuccessful"] = False
        with self.assertRaises(ValueError): self.evaluate()

    def test_scanner_error_notification_fails(self):
        self.report["runs"][0]["invocations"][0]["toolExecutionNotifications"] = [{"level": "error"}]
        with self.assertRaises(ValueError): self.evaluate()

    def test_parser_error_fails_despite_zero_results(self):
        with self.assertRaises(ValueError): self.evaluate({"errors": [{"type": "ParseError"}], "paths": {"scanned": ["a.php", "a.js"]}})

    def test_both_languages_must_have_been_analyzed(self):
        with self.assertRaises(ValueError): self.evaluate({"errors": [], "paths": {"scanned": ["a.js"]}})

    def test_nonzero_scanner_exit_without_findings_fails(self):
        for status in ["1", "2", "3", "", "not-a-status"]:
            with self.assertRaises(ValueError): self.evaluate(status=status)

    def test_empty_or_missing_rules_fail(self):
        self.report["runs"][0]["tool"]["driver"]["rules"] = []
        with self.assertRaises(ValueError): self.evaluate()

    def test_missing_and_malformed_reports_fail(self):
        with self.assertRaises(OSError): gate.evaluate(self.root / "missing.sarif")
        bad = self.root / "bad.sarif"
        bad.write_text("not-json", encoding="utf-8")
        with self.assertRaises(ValueError): gate.evaluate(bad)

    def test_cli_returns_nonzero_for_a_finding_and_zero_for_complete_clean_scan(self):
        self.evaluate()
        command = [sys.executable, str(Path(gate.__file__)), '--sarif', str(self.root / 'report.sarif'), '--semgrep-json', str(self.root / 'report.json'), '--scanner-exit', str(self.root / 'exit.txt')]
        self.assertEqual(subprocess.run(command, capture_output=True).returncode, 0)
        self.report['runs'][0]['results'] = [{'ruleId': 'sql-injection', 'level': 'note'}]
        self.evaluate(status='1')
        result = subprocess.run(command, capture_output=True, text=True)
        self.assertEqual(result.returncode, 1)
        self.assertIn('sql-injection', result.stdout)

    def test_json_and_sarif_cannot_disagree(self):
        self.report['runs'][0]['results'] = [{'ruleId': 'sql-injection'}]
        with self.assertRaises(ValueError): self.evaluate({'errors': [], 'results': [], 'paths': {'scanned': ['a.php', 'a.js']}})

    def test_missing_error_status_is_not_a_successful_scan(self):
        for status in [None, {}]:
            with self.assertRaises(ValueError): self.evaluate({'errors': status, 'results': [], 'paths': {'scanned': ['a.php', 'a.js']}})

    def test_codeql_rulepack_extensions_are_a_valid_catalog(self):
        run = self.report['runs'][0]
        run['tool'] = {'driver': {'name': 'CodeQL', 'semanticVersion': '2.27.4', 'rules': []}, 'extensions': [{'name': 'codeql-action/pr-diff-range', 'rules': []}, {'name': 'codeql/javascript-queries', 'rules': [{'id': 'sql-injection'}]}, {'name': 'codeql/javascript-all', 'rules': []}]}
        run['results'] = [{'ruleId': 'sql-injection', 'message': {'text': 'Review the result'}}]
        sarif = self.root / 'codeql-rulepacks.sarif'
        sarif.write_text(json.dumps(self.report), encoding='utf-8')
        findings, _ = gate.evaluate(sarif)
        self.assertEqual(len(findings), 1)
        self.assertIn('sql-injection', findings[0])
        run['results'] = []
        sarif.write_text(json.dumps(self.report), encoding='utf-8')
        self.assertEqual(gate.evaluate(sarif), ([], {}))

    def test_empty_rulepacks_and_missing_invocation_cannot_pass(self):
        run = self.report['runs'][0]
        run['tool']['driver']['rules'] = []
        run['tool']['extensions'] = [{'name': 'fake-pack', 'rules': []}]
        with self.assertRaises(ValueError): self.evaluate()
        run['tool']['extensions'][0]['rules'] = [{'id': 'example-security-rule'}]
        del run['invocations']
        with self.assertRaises(ValueError): self.evaluate()

    def test_result_rule_must_exist_in_catalog(self):
        self.report['runs'][0]['results'] = [{'ruleId': 'invented-rule'}]
        with self.assertRaises(ValueError): self.evaluate()


class FullCodeQLTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix='appu-full-codeql-')
        self.sarif = Path(self.temporary.name) / 'report.sarif'
        self.run = {'tool': {'driver': {'name': 'CodeQL', 'semanticVersion': '2.27.4', 'rules': [{'id': 'js/unsafe-jquery-plugin'}]}},
                    'invocations': [{'executionSuccessful': True}], 'results': [],
                    'properties': {'codeqlConfigSummary': {'disableDefaultQueries': False},
                                   'metricResults': [{'ruleId': 'js/summary/lines-of-user-code', 'value': 100, 'baseline': 120}]}}

    def tearDown(self):
        self.temporary.cleanup()

    def evaluate(self):
        self.sarif.write_text(json.dumps({'version': '2.1.0', 'runs': [self.run]}), encoding='utf-8')
        return gate.evaluate(self.sarif, require_full_codeql=True)

    def test_complete_codeql_with_default_queries_and_coverage_passes(self):
        self.assertEqual(self.evaluate(), ([], {}))

    def test_diff_or_overlay_report_cannot_pass_even_without_findings(self):
        for mode in ['diff-informed', 'overlay', 'diff-informed,overlay']:
            self.run['properties']['incrementalMode'] = mode
            with self.assertRaises(ValueError): self.evaluate()

    def test_diff_pack_cannot_pass_by_omitting_mode_metadata(self):
        self.run['tool']['extensions'] = [{'name': 'codeql-action/pr-diff-range', 'rules': []}]
        with self.assertRaises(ValueError): self.evaluate()

    def test_missing_source_metrics_or_disabled_defaults_fail(self):
        self.run['properties']['metricResults'][0]['baseline'] = 0
        with self.assertRaises(ValueError): self.evaluate()
        self.run['properties']['metricResults'][0]['baseline'] = 120
        self.run['properties']['codeqlConfigSummary']['disableDefaultQueries'] = True
        with self.assertRaises(ValueError): self.evaluate()

    def test_another_engine_cannot_attest_full_codeql(self):
        self.run['tool']['driver']['name'] = 'Semgrep'
        with self.assertRaises(ValueError): self.evaluate()


class ExactReviewedFindingTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temporary = tempfile.TemporaryDirectory(prefix='appu-reviewed-security-')
        cls.root = Path(cls.temporary.name)
        for folder in ['src', 'global', 'tests']:
            (cls.root / folder).mkdir()
        cls.command('init', '-q')
        for name, text in [('src/example.php', '<?php $query = "SELECT 1";'), ('global/validator.php', '<?php function validate() { return true; }'), ('tests/proof.php', '<?php assert(true);')]:
            (cls.root / name).write_text(text, encoding='utf-8')
        cls.original = cls.commit()
        (cls.root / 'src/example.php').write_text('<?php $query = "SELECT 2";', encoding='utf-8')
        cls.source_changed = cls.commit()
        (cls.root / 'global/validator.php').write_text('<?php function validate() { return false; }', encoding='utf-8')
        cls.dependency_changed = cls.commit()
        (cls.root / 'tests/proof.php').write_text('<?php assert(false);', encoding='utf-8')
        cls.evidence_changed = cls.commit()
        cls.command('checkout', '-q', cls.original)
        (cls.root / 'metadata.txt').write_text('A later commit with identical reviewed source bytes.', encoding='utf-8')
        cls.metadata_changed = cls.commit()

    @classmethod
    def command(cls, *args):
        return subprocess.run(['git', '-C', str(cls.root), *args], check=True, capture_output=True).stdout.decode().strip()

    @classmethod
    def commit(cls):
        cls.command('add', '.')
        cls.command('-c', 'user.name=Security QA', '-c', 'user.email=qa@example.test', '-c', 'commit.gpgsign=false', 'commit', '-qm', 'fixture')
        return cls.command('rev-parse', 'HEAD')

    @classmethod
    def tearDownClass(cls):
        cls.temporary.cleanup()

    def setUp(self):
        self.sources = gate.GitSources(self.root, self.original)
        self.result = {'ruleId': 'example.sql-sink', 'message': {'text': 'Review this sink'}, 'locations': [{'physicalLocation': {'artifactLocation': {'uri': 'src/example.php'}, 'region': {'startLine': 1, 'startColumn': 7, 'endLine': 1, 'endColumn': 19}}}], 'fingerprints': {'matchBasedId/v1': 'requires login'}}
        self.finding = gate.finding_identity('Semgrep OSS', self.result)
        self.finding['scanner_version'] = 'test.1'
        self.entry = {'id': 'review-001', 'engine': 'semgrep', 'rule_id': 'example.sql-sink', 'path': 'src/example.php', 'fingerprint': self.finding['fingerprint'], 'source_sha256': self.sources.digest('src/example.php'), 'reviewed_commit': self.original, 'scanner_version': 'test.1', 'dependencies': [{'path': 'global/validator.php', 'sha256': self.sources.digest('global/validator.php')}], 'dependency_review': 'The validator controls which values reach this exact SQL sink.', 'reviewer': 'Security QA reviewer', 'reviewed_on': '2026-11-07', 'rationale': 'The fixture demonstrates a constant SQL statement with no untrusted interpolation.', 'disposition': 'false_positive', 'evidence': [{'path': 'tests/proof.php', 'sha256': self.sources.digest('tests/proof.php'), 'description': 'The reviewed regression checks a constant SQL statement.'}]}
        self.manifest = self.root / 'reviews.json'

    def apply(self, findings=None, sources=None):
        self.manifest.write_text(json.dumps({'schema_version': 1, 'findings': [self.entry]}), encoding='utf-8')
        return gate.reviewed_findings([self.finding] if findings is None else findings, self.manifest, sources or self.sources, {'semgrep'})

    def test_exact_review_accepts_only_that_finding(self):
        pending, accepted = self.apply()
        self.assertEqual(pending, [])
        self.assertEqual(accepted, [self.finding])
        self.assertNotEqual(self.finding['fingerprint'], 'requires login')

    def test_later_commit_with_identical_source_and_dependencies_preserves_review(self):
        pending, accepted = self.apply(sources=gate.GitSources(self.root, self.metadata_changed))
        self.assertEqual(pending, [])
        self.assertEqual(len(accepted), 1)

    def test_additional_finding_remains_blocking(self):
        new = gate.finding_identity('Semgrep OSS', {**self.result, 'ruleId': 'another.security-rule'})
        new['scanner_version'] = 'test.1'
        pending, accepted = self.apply([self.finding, new])
        self.assertEqual(pending, [new])
        self.assertEqual(accepted, [self.finding])

    def test_changed_fingerprint_does_not_inherit_review(self):
        self.finding['fingerprint'] = '0' * 64
        with self.assertRaises(ValueError): self.apply()

    def test_changed_committed_source_fails(self):
        with self.assertRaises(ValueError): self.apply(sources=gate.GitSources(self.root, self.source_changed))

    def test_changed_critical_helper_fails(self):
        # Match the new source explicitly, keeping the original dependency hash.
        sources = gate.GitSources(self.root, self.dependency_changed)
        self.entry['source_sha256'] = sources.digest('src/example.php')
        self.entry['reviewed_commit'] = self.source_changed
        with self.assertRaises(ValueError): self.apply(sources=sources)

    def test_changed_evidence_fails(self):
        sources = gate.GitSources(self.root, self.evidence_changed)
        self.entry['source_sha256'] = sources.digest('src/example.php')
        self.entry['dependencies'][0]['sha256'] = sources.digest('global/validator.php')
        self.entry['reviewed_commit'] = self.dependency_changed
        with self.assertRaises(ValueError): self.apply(sources=sources)

    def test_stale_review_fails_when_finding_disappears(self):
        with self.assertRaises(ValueError): self.apply([])

    def test_scanner_version_change_requires_review(self):
        self.finding['scanner_version'] = 'test.2'
        with self.assertRaises(ValueError): self.apply()

    def test_wildcard_and_outside_paths_rejected(self):
        for path in ['src/*.php', '../private.php', '/private.php', 'src/../a.php', 'src\\a.php']:
            self.entry['path'] = path
            with self.assertRaises(ValueError): self.apply()

    def test_uncommitted_or_invented_hash_cannot_attest_source(self):
        self.entry['source_sha256'] = '0' * 64
        with self.assertRaises(ValueError): self.apply()

    def test_missing_review_metadata_or_unpinned_proof_rejected(self):
        for key in ['reviewer', 'rationale', 'dependency_review', 'scanner_version']:
            old = self.entry[key]
            self.entry[key] = ''
            with self.assertRaises(ValueError): self.apply()
            self.entry[key] = old
        self.entry['evidence'][0]['sha256'] = ''
        with self.assertRaises(ValueError): self.apply()

    def test_review_date_must_be_valid_iso_date(self):
        for day in ['20261107', '2026-02-30', '', 'tomorrow']:
            self.entry['reviewed_on'] = day
            with self.assertRaises(ValueError): self.apply()

    def test_duplicate_manifest_ids_rejected(self):
        self.manifest.write_text(json.dumps({'schema_version': 1, 'findings': [self.entry, self.entry]}), encoding='utf-8')
        with self.assertRaises(ValueError): gate.reviewed_findings([self.finding], self.manifest, self.sources)

    def test_default_cli_still_blocks_and_exact_manifest_does_not_mutate_raw_report(self):
        self.apply()
        report = {'version': '2.1.0', 'runs': [{'tool': {'driver': {'name': 'Semgrep OSS', 'semanticVersion': 'test.1', 'rules': [{'id': 'example.sql-sink'}]}}, 'invocations': [{'executionSuccessful': True}], 'results': [self.result]}]}
        sarif = self.root / 'reviewed.sarif'
        sarif.write_text(json.dumps(report), encoding='utf-8')
        original_bytes = sarif.read_bytes()
        command = [sys.executable, str(Path(gate.__file__)), '--sarif', str(sarif)]
        self.assertEqual(subprocess.run(command, capture_output=True).returncode, 1)
        command += ['--reviewed-allowances', str(self.manifest), '--source-root', str(self.root), '--source-commit', self.original]
        result = subprocess.run(command, capture_output=True, text=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn('1 exact reviews', result.stdout)
        self.assertEqual(sarif.read_bytes(), original_bytes)


if __name__ == "__main__":
    unittest.main()
