# APPU stability audit — 5 November 2026 (America/Lima)

## Protected behavior

CPE invoices, receipts and credit notes were prioritized. Existing documents were checked using authenticated GET requests only. No tax document was issued, resent, cancelled or reconciled during this audit. No production database migration was applied. Unrelated local worktree changes were preserved.

## Findings and corrections

- The CPE submit route required GRE-only variables, including a token URL absent from the example configuration. A dedicated transport factory now creates CPE SOAP transport without evaluating GRE/Identity configuration.
- Identity lookup ran before API-key authentication. It now authenticates first and remains disabled by default until a provider and the remaining controls are validated.
- GRE could reach the productive REST service under a Beta CPE configuration. Independent guards now block that path. GRE reconciliation cannot fall through to the CPE SOAP consultant.
- A product description containing `DespatchAdvice` could switch the transport. XML root name and namespace now determine the document type.
- The ERP artifact endpoint emitted a malformed `Content-Length` header. It is corrected. The state endpoint returns only its intended document fields and treats nonexistent CPE series as disabled.
- Windows release packaging changed official XSL line endings, invalidating the recorded checksums. `.gitattributes` preserves those files exactly; their integrity is checked after unpacking.
- Concurrent HTTP checks reproduced PDO `2002: Operation not permitted`. Hostinger documents a 20-new-connections/second account limit. The API and ERP now retry only that exact PDO constructor failure, up to three attempts with one-second pauses. SQL statements, transactions and SUNAT requests are never replayed by this mechanism. Persistent connections were not enabled.

Hostinger reference: https://www.hostinger.com/support/how-to-fix-mysql-operation-not-permitted-error/

## Verification evidence

- API: 53 PHPUnit cases, 193 assertions, passing locally and on Hostinger PHP 8.3.36.
- GitHub Actions: PHP 8.1, 8.3 and 8.4 with disposable MariaDB databases; lint, unit regressions, state-store transitions and authenticated CPE submit replay all passed for `db9c7ec56ab7`.
- The submit replay test uses a real temporary local database with external SOAP blocked. Invoice, receipt and credit note replay twice with unchanged final states, attempts and events, without GRE/Identity configuration.
- QA repository -> mapper -> signing -> XSD passed for all three CPE types. QA series and documents were rolled back; the test certificate is generated temporarily.
- ERP: 62 UI tests passed from a clean archive of the committed release, with zero skipped cases. They mock AJAX and do not certify SUNAT acceptance.
- ERP state/XML/CDR HTTP regressions and bounded connection-retry tests passed. Read-only local QA checks for inventory, finance, document flow and management also passed.
- Before promotion, a private candidate ran the real Application against existing Hostinger documents. Preview, XML and signed XML matched the live baseline exactly for four CPE records; all signed files passed XSD. Four archived CDRs contain SUNAT response code 0.
- Baseline CPE database snapshot: four records, fourteen events, hash `9014b8535da2818c8805b2c124cbb4bcb85cb776ed5fcc0930c1d36429aa587f`. This covers status/attempt metadata and artifact hashes, not a full database backup.

## Release controls and scope

Candidates are pushed to feature branches and tested before merging master. The ERP's Hostinger checkout was observed updating after master was pushed; treat a master push as a production deployment trigger. The API uses immutable commit-named releases and atomic `current` symlink promotion. Previous releases and an ERP artifact rollback archive are retained.

GRE XML/preview remains experimental, GRE transmission is disabled, Identity is disabled pending provider validation, and SIRE/Factoring are not implemented. Their future activation needs its own tests and approval of real tax operations. This audit does not certify a new SUNAT submission, unrestricted load capacity or every business scenario of the ERP.

## Final promotion and post-deployment results

- API active release: `db9c7ec56ab7`; ERP functional release: `a63d42e`. Both were published to their GitHub master branches after candidate verification.
- CI evidence: https://github.com/emco10/appu-sunat-api/actions/runs/42155110928 — all three PHP jobs passed.
- Before promotion, the retry-enabled API and ERP candidates passed 24 simultaneous HTTPS checks through temporary GET-only gateways. Both gateways enforced their normal authentication; the ERP gateway rejected POST.
- After promotion, the same 24 checks ran simultaneously through the normal production routes: every response was HTTP 200, every XML/CDR hash matched the baseline, and CPE states/attempt counts remained unchanged. Signed XML passed XSD. Health and readiness returned HTTP 200.
- CPE database snapshot remained four records/fourteen events with the exact baseline hash above. The existing CDR response codes remained 0. These are historical acceptances; no fresh submission was made for this audit.
- Temporary public gateways were removed and their absence was verified on disk. The temporary PHP listener was stopped. The public API bootstrap was restored byte-for-byte after diagnostic logging; private logs and rollback artifacts remain outside public_html.
- The final report commit changes documentation only; it does not change the functional ERP release identified above.
