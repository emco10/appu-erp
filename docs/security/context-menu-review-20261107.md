# Context-menu review — 7 November 2026

The first full master CodeQL report (`42421200811`) contained 12 findings rather than the eight shown by the preceding diff-informed PR report. Four additional `js/unsafe-jquery-plugin` occurrences were in unchanged plugins. The release workflow now requires a full report on PRs as well as master; no plugin directory or query was excluded.

## Positioning helper: fixed

`plugins/context-menu/jquery.ui.position.js` is the existing jQuery UI 1.12.1 positioning module with the exact six-line upstream backport for [CVE-2021-41184](https://github.com/jquery/jquery-ui/commit/effa323f1505f2ce7a324e4f429fa9032c72f280). The previous `$(options.of)` could create an HTML element when handed an HTML string. String values now go through `$(document).find(options.of)`; DOM, window and event inputs retain their existing behavior. The version header remains 1.12.1 because this is a targeted patch, not a package-wide upgrade.

This occurrence is fixed in runtime code and is not added to the reviewed-finding allowance manifest.

## Context-menu plugin: three scoped observations

The unchanged jQuery contextMenu 2.9.0 plugin accepts developer configuration. APPU initializes it only through `$(fixedTableSelector).contextMenu(options)` in five screens: guides, purchase summaries, quotation summaries, sales orders and order summaries. In this wrapper the plugin overwrites `operation.context` with the existing jQuery collection before passing it to `$.contextMenu('create', operation)`. API record values do not choose the context.

| Original line | Traced value |
| --- | --- |
| 1679 | `o.context` is the wrapper's existing table collection; converting it to jQuery does not parse an HTML string. |
| 1681 | The prior `.get(0)` has replaced `o.context` with a DOM node. The jQuery call compares that node with `document`. |
| 1803 | The destroy branch reuses the normalized context node and checks it against the locally configured menu selector. |

These are conditional findings for the current APPU callers, not a claim that arbitrary untrusted context-menu configuration is safe. The exact reviewed entries bind the plugin, positioning helper, footer load order, all five initializer files and the real-plugin regression evidence. Changes to those sources invalidate the review.

The regression suite reproduces the old positioning HTML-construction path and checks the patched selector path. It exercises DOM, jQuery and CSS-selector positioning and the actual table-bound menu create/show/action/hide/destroy behavior with the local jQuery and both minified and unminified context-menu plugins, including removal of the registered menu. The source map's embedded source must equal the reviewed unminified source after line-ending normalization. The test is executable evidence for this scope; it does not permit passing external data as plugin configuration.
