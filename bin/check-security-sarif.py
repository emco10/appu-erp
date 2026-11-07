"""Fail closed on missing/failed scans or any SARIF finding, including suppressed findings."""
from __future__ import annotations

import argparse
import json
from pathlib import Path
import sys


def load_object(path: Path) -> dict:
    value = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(value, dict):
        raise ValueError(f"Invalid report object: {path.name}")
    return value


def evaluate(sarif: Path, semgrep_json: Path | None = None, scanner_exit: Path | None = None) -> tuple[list[str], dict]:
    report = load_object(sarif)
    if report.get("version") != "2.1.0" or not isinstance(report.get("runs"), list) or not report["runs"]:
        raise ValueError("Missing SARIF runs or unsupported report format")
    findings = []
    for run in report["runs"]:
        driver = run.get("tool", {}).get("driver", {})
        if not driver.get("name") or not isinstance(driver.get("rules"), list) or not driver["rules"]:
            raise ValueError("A scan without an identified tool and rules cannot pass")
        for invocation in run.get("invocations", []):
            if invocation.get("executionSuccessful") is False:
                raise ValueError("Scanner reported an incomplete or failed execution")
            for notification in invocation.get("toolExecutionNotifications", []):
                if notification.get("level") == "error":
                    raise ValueError("Scanner reported an analysis error")
        results = run.get("results")
        if not isinstance(results, list):
            raise ValueError("Missing SARIF results array")
        for result in results:
            # Suppressions, baselines and severity do not implicitly waive a finding.
            physical = (result.get("locations") or [{}])[0].get("physicalLocation", {})
            location = str(physical.get("artifactLocation", {}).get("uri", "unknown-file"))
            line = physical.get("region", {}).get("startLine", "?")
            findings.append(f"{result.get('ruleId', 'unknown-rule')} at {location}:{line}")
    coverage = {}
    if semgrep_json is not None:
        semgrep = load_object(semgrep_json)
        if semgrep.get("errors"):
            raise ValueError("Semgrep reported parsing, rule or analysis errors; review its JSON artifact")
        paths = semgrep.get("paths", {}).get("scanned")
        if not isinstance(paths, list) or not all(isinstance(path, str) for path in paths):
            raise ValueError("Semgrep did not provide scanned-file coverage")
        coverage = {"php": sum(path.endswith(".php") for path in paths), "javascript": sum(path.endswith(".js") for path in paths)}
        if not all(coverage.values()):
            raise ValueError("Semgrep must actually scan both PHP and JavaScript")
    if scanner_exit is not None:
        status = scanner_exit.read_text(encoding="utf-8").strip()
        if status != "0" and not (status == "1" and findings):
            raise ValueError("Scanner failed or did not complete; review its exit status and artifacts")
    return findings, coverage


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--sarif", type=Path, required=True)
    parser.add_argument("--semgrep-json", type=Path)
    parser.add_argument("--scanner-exit", type=Path)
    args = parser.parse_args()
    try:
        findings, coverage = evaluate(args.sarif, args.semgrep_json, args.scanner_exit)
    except (OSError, ValueError, TypeError, KeyError, AttributeError) as error:
        print(f"FAIL security analysis: {error}", file=sys.stderr)
        return 1
    for finding in findings:
        print("FINDING " + finding)
    print(f"Security analysis: {len(findings)} finding(s); scanned coverage: {coverage or 'see SARIF'}")
    return 1 if findings else 0


if __name__ == "__main__":
    sys.exit(main())
