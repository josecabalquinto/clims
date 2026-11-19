# Dashboard

The Dashboard uses the existing application shell, teal palette and Poppins typography. Four equipment totals share a compact summary surface. The main column contains the equipment-status and most-borrowed charts. Recent borrowing occupies the former activity card on the right, using its compact list layout. Sections stack on smaller screens.

The route remains `/dashboard` (`dashboard`), implemented by `resources/views/pages/⚡dashboard.blade.php`. Styles live in `resources/css/dashboard.css`, imported through the application JavaScript entry point.

## Totals and equipment status

| Total | Definition |
| --- | --- |
| Total equipment | Sum of registered equipment quantities, counting physical units; inventory-record count appears as supporting text |
| Available units | Sum of usable available quantities |
| Borrowed units | Sum of borrowing-item quantities minus returned quantities, including overdue loans |
| Defective units | Sum of defective units currently on hand |
| Active loans | Transactions with at least one outstanding unit |
| Overdue loans | Active loans whose expected return time is earlier than the current time |

The equipment-status doughnut displays Available, Borrowed and Defective quantities. Its legend includes counts, percentages and links to filtered inventory. The circle's center shows the sum of these three quantities. An empty inventory produces a neutral ring with zero values and an explanatory message. SVG titles provide chart descriptions, and the text legend exposes the same information without relying on color.

Equipment counters and borrowing records stay consistent through the inventory and borrowing services. Multiple outstanding items in one loan count as one active/overdue transaction. Completed loans contribute to neither loan total; overdue takes precedence over partially returned in loan labels.

## Most borrowed equipment

The horizontal bar chart ranks the five equipment records with the greatest sum of original loan-item quantities in the rolling last 30 days, using borrowing time in UTC. Future borrowing times are excluded. Returned units still count: this measures borrowing demand, rather than current outstanding stock.

Records are grouped by equipment ID, preserving separate asset tags even when names match. Ties use ascending equipment ID. Bars scale relative to the leading equipment record; visible counts state the actual units borrowed. Equipment names link to their detail pages. No activity in the period produces an empty state instead of misleading bars.

The chart is a ranking, not a time-series trend. It uses native HTML/CSS and SVG without another chart dependency.

## Recent borrowing

The latest five transactions appear by borrowing time, then ID descending. Each list entry includes a linked borrower name, equipment summary, outstanding units, current status, loan number and return deadline. Active and overdue loan counts remain above the list, with an overdue-history link.

The recent-activity widget and its Dashboard queries have been removed. Activity logs themselves remain untouched and the View Log Details module remains accessible from the sidebar.

## Refresh, access and validation

Data is read on page load and Refresh. There is no background polling or persistent cache. SQL aggregates calculate totals and borrowing rankings; bounded queries eager-load displayed relationships. Text is escaped by Blade, and dates display in `config('clims.display_timezone')`, defaulting to Asia/Singapore.

Authentication, active-account and verification route protection remains. The page refreshes account state and checks its access gate on Livewire requests. Both active staff and Super Admins have access.

Dashboard tests cover totals, empty states, overdue boundaries, recent-loan limits, safe labels, retained audit history, authorization and refresh. Ranking tests cover units versus transaction counts, returned units, the exact 30-day boundary, excluded older/future loans, identical equipment names, deterministic ties, top-five limits and refresh. Desktop, mobile and dark-theme browser checks used an isolated temporary database.

No migrations or new dependencies were required. View Log Details and User Management are implemented; see ACTIVITY-LOGS.md and USER-MANAGEMENT.md. Next: end-to-end acceptance checks and release configuration.
