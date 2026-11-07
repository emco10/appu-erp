# PHP review of Semgrep run 42369463644

This review covers exactly 105 PHP results in 44 files from commit
`ed6f667ab3b604b09b0815466be308b5293f6835`: 36 `tainted-callable`,
33 `tainted-sql-string`, 29 `echoed-request`, 5 `tainted-filename` and
2 audit-level `unlink-use` results. The adjacent JSON preserves each scanner
identity, region and SHA256 of the analyzed Git blob. It records review
recommendations; it is not an executable scanner suppression or gate allowance.
Any changed source, dependency or scanner location must be reviewed again on
the final commit and scan. The CE text `requires login` is not a fingerprint.

## SQL and callable results

The reported callable locations are interpolated SQL arguments to fixed PDO
methods, not invocation of PHP functions selected by a request. All SQL values
in these locations are either prepared parameters or validated integers. The
review traced the input before the sink, rather than treating `prepare()` as
sufficient by itself.

- Purchase, quotation, invoice, receipt, credit-note, order and product filters:
  `ESTADO` is `ALL` or passes `FILTER_VALIDATE_INT`; malformed values exit
  before the SQL query. Document IDs use PDO placeholders. Order types become
  literal `1`, `2` or an empty filter through an explicit switch.
- Approval/status changes and document maps: table, detail and column names
  come from in-file maps with an allowed document-type key. Values remain
  bound parameters.
- Role permissions: the `IN (...)` syntax contains only repeated `?`
  placeholders from `array_fill`; permission IDs are supplied to `execute`.
- GRE preparation: update columns are selected from a fixed lengths map plus
  the literal `gross_weight` and `weight_unit` fields, never request key names.
- Product uniqueness: column names are the literal `code`, `name` and
  `barcode` values in an in-file array; values use placeholders.

`php tests/security-php-input-boundaries.php` exercises 15 actual endpoints
over loopback HTTP with a fake PDO, without SQL/network access. It verifies
rejection of SQL/array status payloads, parameter binding of raw document ID
payloads, and absence of those payloads from SQL syntax. This is regression
evidence for the reviewed input boundaries, not a claim of exhaustive SQL
injection coverage across the application.

## Output results

Of the 29 output findings, 26 are JSON responses with an explicit JSON media
type. The real connection sends `X-Content-Type-Options: nosniff`. The HTTP
regression verifies both headers and round-trips an HTML payload through the
reported purchase-report echo sink as JSON data. JSON encoding alone is not
the security argument; the media type, nosniff and separate client-side sink
review are necessary boundaries.

Two results are attachment responses: GRE XML/CDR and SIRE ZIP. They have
allowlisted filenames, checked base64/size/SHA256, fixed MIME types,
`Content-Disposition: attachment` and nosniff. SIRE also checks the ZIP magic.
`tests/sire-bff-http.php` checks unsafe names, corrupted hashes and download
headers. The remaining result is the script URL in the template: view/script
components pass a character allowlist, view resolution is confined by
`realpath`, and `app_base_path()` encodes each path component with
`rawurlencode`. It is not an arbitrary request string inserted into markup.

The review additionally found that 14 legacy query/list endpoints omitted the
JSON media type. They now explicitly send JSON without changing response
values or structure. Their JavaScript callers require compatibility with
jQuery's automatic JSON decoding; that is verified separately before release.

## File results and trust boundaries

Four CPE file findings refer to the same database-selected artifact path. The
request supplies an allowlisted source type, a positive integer ID and an
allowlisted XML/CDR kind, all bound in the SQL query. It cannot supply a path.
No ERP endpoint writes `xml_path` or `cdr_path`. The paired API writes these
fields through `SubmissionService`, `DocumentStorage` and `SunatDocumentStore`;
`DocumentStorage` permits only alphanumeric/hyphen XML/ZIP basenames under the
server's configured storage directory. This external writer contract was
reviewed at API commit `9f6da6edf008da39b2d53dea2aaa794dc1cb3410`.

This assessment assumes integrity of that database and trusted API storage
configuration. The ERP currently does not independently restrict artifact
paths to configured canonical storage roots. That is a remaining defense in
depth task, not a demonstrated request-controlled SSRF or file-read exploit.
Introducing a guessed directory allowlist could break existing artifacts;
the deployed storage roots and historical paths must be confirmed first.
The template file-existence finding has the same route/script allowlists and
filesystem confinement described above; its lookup path has a fixed local
application directory and cannot be a remote URL.

## Temporary QR files

The two unlink findings do not receive a request-selected filename. A separate
local filesystem race was nevertheless found: the code deleted the file
reserved by `tempnam`, then wrote to an unreserved `.png` sibling. The fix
writes to the original reserved file and gives FPDF the explicit PNG type.
Cleanup remains in `finally`.

`php tests/security-qr-tempfile.php` generates real invoice, receipt and
credit-note PDFs with embedded QR images, observes the reserved files, checks
normal and exceptional cleanup, and verifies an unrelated file is unchanged.
It checks mode `0600` on POSIX; Windows permission bits do not represent that
Unix permission model. The test enables the already installed GD DLL only in
its Windows child process when necessary. Linux CI must provide PHP GD.
PHP documents the unique-file creation and permission contract in the
[tempnam manual](https://www.php.net/manual/en/function.tempnam.php).

No production requests, SUNAT submissions, database changes, scanner rule
changes or gate suppressions were performed for this review.
