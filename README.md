# Trans Nzoia County Recruitment Portal

This is the modernized official recruitment and HR administrative portal for the County Government of Trans Nzoia.

## Latest Updates & Patches (Final Refinement Phase)
During the final review phases, the following core logic was upgraded and implemented to ensure complete stability and GDPR/PII compliance:

- **Legacy & Authentic User Merging:** Upgraded SQL handlers across `status.php`, `shortlist.php`, and `admin/interviews.php` to use dynamic `LEFT JOIN` and `COALESCE` routines. This allows the system to seamlessly route, render, and fallback on raw application data for mock/guest records without requiring a fully dedicated User Authentication node.
- **Dynamic Shortlists Rendering:** Restructured the public-facing Shortlists page so that any candidate transitioning from "Shortlisted" into an active "Interview Scheduled" state remains legally preserved on the public shortlists board.
- **PII Obfuscation:** Stripped exposed personal demographics from the public `shortlist.php` page. Native Sub-county and Ward mappings are retained, while the candidate's National ID acts as the primary validation anchor natively obscured to `****` for safety.
- **Native PDF Export:** Implemented a new `public_shortlist_print.php` subsystem mapping directly to a CDN instance of `html2pdf.js`. Clicking "Download PDF Form" dynamically cascades the DOM into an Official Government PDF physical memory payload and automatically prompts a hard download onto the user's hard drive—resolving previous complications with blocked browser `window.print()` popups.

## Setup
The fully structured relational database map is backed up within the `/sql` directory (`tnc_recruitment_backup.sql`).
All initial system configurations rely on local `.htaccess` routing capabilities.

> **Note:** Do not forget to adjust directory permissions to 0755 on `/uploads` when deploying to your remote Linux production environments!
