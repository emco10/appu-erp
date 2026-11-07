# Security analysis in CI

At the initial diagnosis on 7 November 2026, GitHub reported APPU's repository as private, and authenticated administrator reads of its code-scanning endpoints returned HTTP 403. The previous CodeQL run completed all 73 JavaScript files, then failed uploading SARIF. That workflow did not retain its report as an artifact. A later read reported public visibility; this workflow neither changes visibility nor enables a paid feature. Its CodeQL guard checks explicitly for public visibility or a verified existing license.

GitHub documents that private code scanning requires GitHub Code Security. The CodeQL CLI license also restricts private-repository use; disabling upload alone is not a free private-repository alternative. This change does not alter repository visibility, enable paid products or start a trial.

The always-running security job uses Semgrep Community Edition 1.184.0 with its open source engine. It scans all first party PHP and JavaScript in `global`, `modules`, `ajax`, `js` and `views`. Exclusions cover copied third party directories (`vendor`, `plugins`, `libs`) and explicitly named minified assets. It avoids heuristic minification exclusions that could skip compact PHP source. No rule, severity, existing finding or `nosem` suppression is exempted.

The scanner executes locally on the GitHub runner without a Semgrep account, token, cloud scan or telemetry. PHP and JavaScript community rules are downloaded at immutable commit `16b9e6d45f10649f6a531ec4838ed9924372c3bc`, preserving the upstream license. These rules are source available under Semgrep Rules License 1.0, which permits royalty-free internal business use. APPU does not redistribute them or offer them as a scanning service.

`--error --strict` identifies findings and analysis errors. Exit 1 denotes findings and is passed to the mandatory gate; other nonzero exits fail analysis immediately. The independently tested gate rejects missing/invalid SARIF, mismatched JSON/SARIF result counts, failed execution, parsing errors and empty PHP/JavaScript coverage. Every new or unreviewed finding blocks CI, including notes, existing findings and suppressed results. Complete SARIF/JSON artifacts and rule provenance are retained for seven days even when analysis fails.

Without a review manifest, every finding blocks the gate. The optional `.github/security-reviewed-findings.json` supports individual, reviewed false positives or audit observations; it never filters the raw reports. Each entry binds the exact tool, scanner version, rule, repository path and locally generated finding fingerprint to the SHA-256 of the complete committed file, its critical helper dependencies and its evidence files. An entry also requires the reviewed Git commit, reviewer, review date, explicit disposition, dependency assessment, rationale and proof description. An empty dependency list still needs a written dependency assessment.

Semgrep CE reports the literal `requires login` as its native fingerprint without an account. APPU does not use that sentinel or request a login. It derives a SHA-256 from the tool, rule, path, precise source region, message and available genuine native fingerprints. It separately checks committed Git bytes. Source, dependency or evidence changes invalidate the review. Duplicate identities, stale entries, scanner-version changes, malformed manifests and broad wildcard paths also fail. A merge commit with identical reviewed file contents does not invalidate a review. Git checkout retains history for review-source verification.

Both gate commands explicitly use the manifest only if that exact file is committed in the checkout. No default manifest is provided. Entries must be independently reviewed before being committed, and real vulnerabilities must be fixed rather than accepted. Scanner suppressions or baseline fields never count as a review. A green check therefore means a complete scan with zero unreviewed findings for the configured engine and rules, not protection against every possible vulnerability.

CodeQL remains an additional job for public repositories, or with `CODEQL_LICENSED=true` after an administrator verifies an existing compatible license. That variable does not purchase or enable a feature. CodeQL exports and preserves SARIF, applies the same finding gate, and uploads to the security dashboard only for public repositories or with `CODE_SCANNING_UPLOAD=true` after dashboard availability is confirmed. Semgrep always remains required.

Do not hide findings to make CI green. Investigate and fix them, or document a narrow false-positive decision for review before changing a rule or exclusion.

Primary sources verified on 7 November 2026:

- [GitHub private CodeQL availability](https://docs.github.com/en/code-security/reference/code-scanning/troubleshoot-analysis-errors/private-repository-enablement).
- [SARIF uploads without Code Security](https://docs.github.com/en/code-security/reference/code-scanning/sarif-files/troubleshoot-sarif-uploads/ghas-required).
- [CodeQL Action license](https://github.com/github/codeql-action#license) and [analyze inputs](https://github.com/github/codeql-action/blob/v4/analyze/action.yml).
- [Semgrep 1.184.0 release](https://github.com/semgrep/semgrep/releases/tag/v1.184.0) and [CLI flags](https://docs.semgrep.dev/cli-reference).
- [Semgrep engine LGPL license](https://github.com/semgrep/semgrep/blob/develop/LICENSE).
- [Pinned community rules](https://github.com/semgrep/semgrep-rules/tree/16b9e6d45f10649f6a531ec4838ed9924372c3bc) and [internal-use rule license](https://semgrep.dev/legal/rules-license/).
