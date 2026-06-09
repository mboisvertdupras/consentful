# Consent log: collapse the store, then stream the export

Deferred from the 1.0.0 gate (release-first triage) — 2026-06-09 architecture audit.
File:line references describe the tree at audit time and may have drifted.

> **Sequencing note:** implement the streaming export *inside* the merged ConsentLog
> module, not before it — otherwise the generator/batching work lands in
> ConsentLogReader only to be moved when the modules collapse.

## Collapse the consent-log store: five modules and two export-row definitions for one table

**Problem.** One concept — "the Consent log table" — is spread across DatabaseSink
(insert, 16 lines), ConsentLogPurger (delete, 28 lines), ConsentLogReader (select,
parked in Admin/ though it is a Consent concept), and ConsentLogSchema (DDL), each
constructed with the identical `( $wpdb, ConsentLogSchema::table( $wpdb ) )` pair at
three call sites. On top, the export-row shape is defined twice and the exporter
carries a union type to bridge a branch production never takes.

**Evidence.** Repeated ctor pairing: Plugin.php:57
`new ConsentLogPurger( $wpdb, ConsentLogSchema::table( $wpdb ) )`, Plugin.php:94
`new DatabaseSink( $wpdb, ConsentLogSchema::table( $wpdb ) )`, ConsentLogReader.php:18
same pair. Duplicate export shape: `ConsentRecord::to_export_row`
(ConsentRecord.php:43-55) and `ConsentLogReader::to_export_row`
(ConsentLogReader.php:71-83) build the same 9-key row via two code paths.
`ConsentLogExporter::to_csv` accepts `iterable<ConsentRecord|array<string, scalar>>`
and branches `$record instanceof ConsentRecord ? $record->to_export_row() : $record`
(ConsentLogExporter.php:14) — but the only production caller is Admin.php:486
`ConsentLogExporter::to_csv( $this->reader->all_export_rows() )`, which always feeds
arrays; the ConsentRecord branch is exercised only by tests.

**Direction.** Merge DatabaseSink + ConsentLogPurger + ConsentLogReader into one
`Consent\ConsentLog` module implementing Sink, constructed once from `$wpdb` (table
derived internally via the Schema). Delete `ConsentRecord::to_export_row` and narrow
ConsentLogExporter to `iterable<array<string, scalar>>`. The Sink seam stays exactly
as ADR 0002 requires — ConsentLog is simply its built-in implementation, and the
`consentful_sink` filter (Plugin.php:96) is untouched. Target shape:
`Consent/ConsentLog` (store/count/recent/all_export_rows/purge — all SQL touching the
table in one module) + ConsentLogSchema (DDL/formats only) + ConsentLogExporter (pure
CSV over array rows); Plugin wires `new ConsentLog( $wpdb )` once; Admin/ loses its
misplaced Consent module.

**Severity.** medium

**Verifier notes.** Every cited line verified accurate and current. ADR 0002 mandates
only the Sink seam plus a built-in Consent log store, not the five-module
decomposition; the suggestion preserves the seam and the filter. DatabaseSink and
ConsentLogPurger are single-statement pass-throughs whose SQL would reappear exactly
once in the merged module; the production-dead `to_export_row`/union is precisely the
speculative flexibility CLAUDE.md bans. No bug or compliance risk today, but a
dual-maintained auditor-facing data shape (already policed by a dedicated parity test)
plus three re-derived wirings is friction to schedule.

## Consent log CSV export buffers the entire table in memory

**Problem.** The export path materializes every Consent record three times over: an
unbounded `SELECT *` into a PHP array, then a single CSV string, then one echo. The
Consent log grows with every Visitor decision (default retention is 730 days,
ProofConfig.php:10); on a high-traffic site that is millions of rows, and the
auditor-export — the feature that exists precisely for that moment — dies on
memory_limit. The `iterable` return type on `all_export_rows()` shows streaming was
the intended interface, but the implementation buffers.

**Evidence.** src/Admin/ConsentLogReader.php:43-50 `all_export_rows(): iterable` runs
`$this->db->get_results( ... 'SELECT * FROM %i ORDER BY created_at DESC, id DESC' )`
with no LIMIT and returns a fully-built array; src/Consent/ConsentLogExporter.php:11-18
accumulates all lines into one `implode( "\r\n", $lines )` string;
src/Admin/ConsentLogDownload.php:8-13 `stream( string $body )` takes the whole body as
a string and echoes it.

**Direction.** Make `all_export_rows()` a generator batching by primary key
(`WHERE id < %d ORDER BY id DESC LIMIT 1000`), and have the download path `fputcsv()`
each row to `php://output` instead of accumulating a string — the existing iterable
seam on `ConsentLogExporter::to_csv()` already supports this; only
ConsentLogDownload's interface changes from `string $body` to the iterable. Implement
inside the merged ConsentLog module (see above).

**Severity.** medium

**Verifier notes.** Evidence accurate at all cited lines; `wpdb::get_results`
additionally retains `$wpdb->last_result`, so memory is held twice before the CSV
string is built. On a busy install the auditor export OOMs against WP's admin
memory_limit at low hundreds of thousands of rows. No ADR decides this the other way
(ADR 0002/CONTEXT.md only require "exportable for an auditor"), and the fix is not
speculative: the `iterable` return type and the exporter test that already feeds
`to_csv()` a generator (ConsentLogExporterTest.php:122) show the streaming interface
is half-built. Medium because it is an admin export failure (loud, recoverable via
SQL/memory bump), not the compliance-critical client gate.
