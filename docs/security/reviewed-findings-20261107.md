# Exact security finding review — 7 November 2026

This review accompanies the Peru integration release and the fixes for unsafe table rendering, JSON response media types, and QR temporary-file ownership. It is an explanation of source-bound scanner observations, not a claim that the application has no vulnerabilities.

The raw reports from GitHub Actions run `42393727377`, analyzed source `9bfb43c093c4f9ddf8343c79a1f751ee15e36715`, contain 279 Semgrep CE findings and eight CodeQL findings. Reports remain unfiltered in CI artifacts. The exact manifest records individual rule/path/fingerprint identities, complete committed source hashes, dependency hashes, evidence hashes, reviewer, rationale and disposition. It does not exempt a rule, severity, directory, or future occurrence. Changing a reviewed source or dependency requires a new review.

The review source `4c859d1` preserves those runtime files and adds further executable DataTables evidence. The later SARIF parser correction accepts CodeQL's extension rule catalog while requiring successful analysis and known rule IDs; it does not suppress findings.

The later [full-source context-menu review](context-menu-review-20261107.md) records observations omitted by the initial diff-informed PR scan, the real positioning fix, and the separate exact review of its guarded replacement occurrence. The initial 279-plus-eight counts below describe the original run, not the final full-source result set.

## PHP observations — 104

| Group | Findings | Source-to-sink assessment |
| --- | ---: | --- |
| SQL values and fixed PDO methods | 45 | IDs/status values are integer validated or bound. Reported callable sites invoke a fixed PDO method. |
| SQL identifiers | 22 | Table and column identifiers come from local fixed maps/branches after allowlist checks; values remain bound. |
| SQL placeholder counts | 2 | Dynamic `IN` text consists of generated `?` placeholders; values are passed separately to `execute`. |
| JSON responses | 26 | Responses have `application/json` and the connection's `nosniff` policy. Browser DOM consumers were reviewed separately. JSON encoding alone is not the output safety argument. |
| Binary downloads | 2 | Fixed attachment media type, allowlisted filename, base64 decoding, length and SHA-256 checks; SIRE also validates ZIP magic. |
| CPE artifact reads | 4 | Requests select an allowlisted type/kind and a bound positive record ID. The path is obtained from the existing CPE ledger populated by the paired API's configured storage writer. |
| Template paths | 2 | Restrictive route/script name checks and real-path confinement; generated URL components are encoded. |
| QR cleanup | 1 | The QR generator retains ownership of the reserved `tempnam` file, embeds it explicitly as PNG, and cleans it in `finally`. |

The PHP review includes actual endpoint/fake-PDO boundary regressions (85 assertions), JSON HTTP and parser compatibility checks, and QR success/failure cleanup tests (13 assertions on Windows, 16 on POSIX). The CPE artifact assessment assumes integrity of the trusted database ledger and paired API writer. Independent canonical-root validation in the legacy ERP artifact reader remains a defense-in-depth improvement; this review does not claim that it already exists or covers a compromised database writer.

## First-party JavaScript observations — 175

Each flagged occurrence was traced to its surrounding function and first-party call sites. The jQuery audit rules report DOM references, fixed selectors and local adapter options, constructed DOM nodes, literal markup, and templates whose variable fields are encoded or converted to numbers. Other observations cover native `confirm`/`prompt`/`alert` interactions and a redirect whose path has two literal choices and whose record ID is encoded.

Dynamic business data enter the affected tables through text renderers or `text()`/`val()`. Shared status badges encode the displayed value; action markup is constructed locally. Identity suggestions use text nodes and require explicit human application. Notification title/message encoding is tested with the actual notification plugin. The exact manifest rationale names the function and source line for each individual finding; this grouping does not stand in for those individual decisions.

## DataTables observations — eight

The deployed core is the official unmodified DataTables 1.13.11 source, SHA-256 `ff925c9eb47d6a57c4ed3755ec07456e44b793dfb02e3ce4ee5e59e298aba9c8`. Two known vulnerabilities reproduced in the previous 1.10.20 core were fixed by the upgrade. See [upgrade and compatibility evidence](../datatables-security-upgrade.md).

The eight remaining CodeQL occurrences were traced individually:

| Occurrences | Assessment |
| --- | --- |
| Two `_stripHtml` spans around line 1521 | The returned value is used for numeric type detection, not as a display HTML security boundary. |
| Longest display around line 5958 | Used for column width calculation; first-party data renderers encode display values. |
| ARIA label around line 6209 | Written through a DOM attribute setter, not interpreted as HTML. |
| Filter cache around line 15057 | Internal text used for searching. |
| Sort key around line 15158 | Internal ordering value. |
| Smart search around line 4595 | User search input is escaped for the generated regular expression. |
| Child-row restoration around line 8436 | APPU does not enable state saving, custom row IDs or child-row restoration. The review is conditional on that configuration. |

The real-core regression suite includes arrays, prototype pollution, malformed/encoded payloads, search/order/width/ARIA paths, raw save-payload preservation, and existing Bootstrap 4/Buttons compatibility. It instruments HTML assignments for the reviewed payload paths. A configuration invariant scans every first-party runtime directory and fails if state saving, custom row IDs or child-row integration is introduced without revisiting the review. These tests support the scoped assessment; they are not proof for every possible DataTables use.

## Release gate meaning

CI must complete both configured scans without analysis errors and report zero unreviewed findings. An exact reviewed observation may remain visible in the raw scanner report or security dashboard. New identities, scanner versions, changed source/dependency/evidence hashes, duplicate identities, or stale manifest entries fail the gate. Source review and executable regressions complement the scanners; neither replaces them.
