# Security analysis in CI

APPU's repository is private and currently has no GitHub Code Security entitlement. Authenticated administrator reads of its code-scanning endpoints return HTTP 403, while ordinary repository administration is available. The previous CodeQL run completed all 73 JavaScript files, then failed uploading SARIF. That workflow did not retain its report as an artifact.

GitHub documents that private code scanning requires GitHub Code Security. The CodeQL CLI license also restricts private-repository use; disabling upload alone is not a free private-repository alternative. This change does not alter repository visibility, enable paid products or start a trial.

The always-running security job uses Semgrep Community Edition 1.184.0 with its open source engine. It scans all first party PHP and JavaScript in `global`, `modules`, `ajax`, `js` and `views`. Exclusions cover copied third party directories (`vendor`, `plugins`, `libs`) and explicitly named minified assets. It avoids heuristic minification exclusions that could skip compact PHP source. No rule, severity, existing finding or `nosem` suppression is exempted.

The scanner executes locally on the GitHub runner without a Semgrep account, token, cloud scan or telemetry. PHP and JavaScript community rules are downloaded at immutable commit `16b9e6d45f10649f6a531ec4838ed9924372c3bc`, preserving the upstream license. These rules are source available under Semgrep Rules License 1.0, which permits royalty-free internal business use. APPU does not redistribute them or offer them as a scanning service.

`--error --strict` fails on findings and analysis errors. An independently tested gate also rejects missing/invalid SARIF, failed execution, parsing errors and empty PHP/JavaScript coverage. Every SARIF finding blocks CI, including notes, existing findings and suppressed results. Private SARIF/JSON artifacts and rule provenance are retained for seven days even when analysis fails. A green check means a completed scan with no reported findings for the configured engine and rules, not protection against every possible vulnerability.

CodeQL remains an additional job for public repositories, or with `CODEQL_LICENSED=true` after an administrator verifies an existing compatible license. That variable does not purchase or enable a feature. CodeQL exports and preserves SARIF, applies the same finding gate, and uploads to the security dashboard only for public repositories or with `CODE_SCANNING_UPLOAD=true` after dashboard availability is confirmed. Semgrep always remains required.

Do not hide findings to make CI green. Investigate and fix them, or document a narrow false-positive decision for review before changing a rule or exclusion.

Primary sources verified on 7 November 2026:

- [GitHub private CodeQL availability](https://docs.github.com/en/code-security/reference/code-scanning/troubleshoot-analysis-errors/private-repository-enablement).
- [SARIF uploads without Code Security](https://docs.github.com/en/code-security/reference/code-scanning/sarif-files/troubleshoot-sarif-uploads/ghas-required).
- [CodeQL Action license](https://github.com/github/codeql-action#license) and [analyze inputs](https://github.com/github/codeql-action/blob/v4/analyze/action.yml).
- [Semgrep 1.184.0 release](https://github.com/semgrep/semgrep/releases/tag/v1.184.0) and [CLI flags](https://docs.semgrep.dev/cli-reference).
- [Semgrep engine LGPL license](https://github.com/semgrep/semgrep/blob/develop/LICENSE).
- [Pinned community rules](https://github.com/semgrep/semgrep-rules/tree/16b9e6d45f10649f6a531ec4838ed9924372c3bc) and [internal-use rule license](https://semgrep.dev/legal/rules-license/).
