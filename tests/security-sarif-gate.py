"""Offline security CI gate regressions; no scanner, credentials or network."""
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
import subprocess
import sys

spec = importlib.util.spec_from_file_location("security_gate", Path(__file__).resolve().parents[1] / "bin/check-security-sarif.py")
gate = importlib.util.module_from_spec(spec)
spec.loader.exec_module(gate)


class SecurityGateTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix="appu-security-gate-")
        self.root = Path(self.temporary.name)
        self.report = {"version": "2.1.0", "runs": [{"tool": {"driver": {"name": "Semgrep", "rules": [{"id": "example-security-rule"}]}}, "invocations": [{"executionSuccessful": True}], "results": []}]}

    def tearDown(self):
        self.temporary.cleanup()

    def evaluate(self, data=None, status="0"):
        sarif = self.root / "report.sarif"
        sarif.write_text(json.dumps(self.report), encoding="utf-8")
        details = self.root / "report.json"
        details.write_text(json.dumps(data if data is not None else {"errors": [], "paths": {"scanned": ["global/a.php", "js/a.js"]}}), encoding="utf-8")
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


if __name__ == "__main__":
    unittest.main()
