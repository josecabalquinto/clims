# CLIMS interface reference

The current interface follows the user's supplied login and application screenshots: a turquoise login panel, a white sign-in panel, a teal sidebar, an off-white workspace, a large welcome heading, and three rounded navigation cards.

## Tokens

| Token | Light value | Role |
| --- | --- | --- |
| brand | #1BC2BE | Turquoise accents |
| brand-deep | #087F80 | Headings, selected navigation, primary actions |
| canvas | #F8F5F8 | Workspace and authentication background |
| surface | #FFFFFF | Forms, cards, and other surfaces |
| ink | #37484A | Body text |
| line | #D7D1D7 | Subtle borders |

Poppins is served through the existing font build integration at weights 400, 500, 600, and 700. No frontend dependency was added. Dark appearance overrides the semantic tokens while retaining the turquoise identity.

## Layout

- Authentication uses a centered card, with a quiet turquoise identity/access panel on the left and the form on the right. The panels stack below 768 pixels.
- The application uses a 244-pixel sidebar, an account menu at the top right, and a spacious main content area. The sidebar becomes a Flux drawer below 1024 pixels.
- Dashboard is the welcome screen; it does not add a separate Home module. Its three shortcuts link to Equipment, Borrow History, and View Log Details.
- The dashboard search filters those module shortcuts locally and explains when there are no matches. It is not an equipment-record search.
- Sidebar navigation keeps the agreed module names and existing authorization. User Management remains exclusive to Super Admins.
- Inactive navigation text uses dark teal against turquoise for legibility; the selected item uses white text against deep teal.
- Shared authentication headers and module placeholders follow the same visual language. Existing profile, password, and appearance settings remain available.

## Implementation boundaries

Email/password sign-in, remember-me, password reset, password confirmation, CSRF protection, and role enforcement retain their existing behavior. The screenshot's social-provider icons and account-registration prompt do not introduce new authentication providers or public registration. Access instructions direct personnel to their Super Admin.

Tokens and scoped Flux overrides live in resources/css/app.css. Existing Blade layouts and components implement the structure; dashboard filtering uses the Alpine integration already supplied by Livewire.

## Equipment workspace

Equipment uses a compact inventory toolbar rather than a permanently expanded form of filters. Availability tabs sit above a single search field; category, location, and condition live in a Filters popover. Selected criteria appear as removable chips. Escape closes the popover and returns keyboard focus to its trigger.

The equipment editor is one surface with identity and laboratory assignment on the left and a tinted stock rail on the right. The rail previews available units as total minus borrowed and defective quantities; final quantities remain validated and calculated by the existing server-side service. Acquisition details and notes use an optional disclosure section. The editor stacks on smaller screens.

Category and location editors share contextual headings, grouped fields, an activation switch, and a clear action footer. Their listings provide status tabs and links from assignment counts to filtered equipment records. Equipment section navigation keeps its active state across Livewire updates.

Equipment styles live in resources/css/equipment.css and use the existing semantic colors and Poppins typeface. They are scoped to Equipment classes and do not alter the application shell or shared palette.

## Review

Browser review covered desktop and 390-pixel mobile layouts in light and dark appearance, with no horizontal overflow. Password visibility, password-reset navigation, the account menu, mobile drawer, module filtering, and empty search results were checked. No browser console errors were observed.

The authenticated shell was reviewed using a temporary rendered fixture with an unsaved user; no account was added to the development database. Normal authentication and authorization were verified by the existing feature suite.

The Equipment redesign was reviewed with a separate temporary SQLite database at desktop and 390-pixel phone widths in light and dark appearance. Browser checks covered filtering, filter chips, successful equipment registration, condition selection, live stock preview, rejected invalid stock, category creation with activation status, and keyboard dismissal. No application JavaScript errors or page-level horizontal overflow were observed. All preview data and its server were removed afterward.
