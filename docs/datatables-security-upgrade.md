# DataTables 1.x security update

The local core `plugins/datatables/jquery.dataTables.js` is the unmodified official
DataTables 1.13.11 distribution, obtained from
https://cdn.datatables.net/1.13.11/js/jquery.dataTables.js on 2026-11-07.
Its MIT license and upstream copyright notice are retained in the file header.
Only the core is updated; the existing Bootstrap and Buttons integrations remain
on their existing versions and are checked against the updated core.

SHA256 of the previous 1.10.20 file:
`434720b876487ef974cf393af0f175746098de95b6cbac6f1f1e061cb43f960e`.
SHA256 of the unmodified 1.13.11 CDN file:
`ff925c9eb47d6a57c4ed3755ec07456e44b793dfb02e3ce4ee5e59e298aba9c8`.

The previous core was reproduced locally with two upstream-confirmed issues:
`render.text()` passed an array through without escaping, creating an `img` node,
and the dotted data setter accepted `__proto__` and modified `Object.prototype`.
The same isolated DOM probes against the new core escaped the array and rejected
the prototype setter. These are regression tests in
`tests/security-datatables-ui.test.cjs`, alongside real DataTables drawing tests
for application data columns and preserved action/status columns.

Upstream evidence:

- https://cdn.datatables.net/1.11.3/ documents the array text renderer XSS fix
  (CVE-2021-23445).
- https://datatables.net/forums/discussion/75727/cve-2020-28458 identifies 1.10.23
  as containing the completed prototype pollution fix.
- https://cdn.datatables.net/1.13.11/ provides release notes for this final 1.x
  release. The latest major release should be assessed separately with its own
  integration and export compatibility checks.

The original six CodeQL findings in 1.10.20 were traced individually: stripping
HTML during numeric type detection (1515), selecting the longest display value
for width measurement (5809), constructing an `aria-label` using `setAttribute`
(6060), preparing a search cache (14737), preparing a sorting key (14832), and
building an already regex-escaped smart-search expression (4462). Those local
transformations are not output sanitizers. Their review did not waive the two
separate, reproduced dependency vulnerabilities, which required this update.
