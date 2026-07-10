# CampusNav Handoff Document

Hello to the new AI! The user has transferred this project to a new location/IDE. Here is a summary of what has been accomplished so far and what needs to be done next.

## Completed Features (Phase 1 & 2)
1. **Core Routing & Maps**: Dijkstra algorithm and Leaflet map rendering are fully functional.
2. **UI Overhaul**: The app features a premium glassmorphic UI, modern typography (Outfit/Inter fonts), micro-animations, and a fully functional Light/Dark mode toggle (saves to `localStorage`).
3. **Authentication**: 
   - Login and registration (`index.php`) are working.
   - We added a "Continue as Guest" flow. Guests can access the map, chat, and notifications, but lack a `user_id`.
   - Admin tools (`coordinate_picker.php` and `edge_linker.php`) have role-based authorization securing them.
4. **AI Assistant (`chat.php`)**:
   - Upgraded from a hardcoded regex bot to a fully conversational AI using Gemini.
   - Handles natural greetings, "Where am I" logic (prompts for nearby room), and routes you to facilities dynamically.
5. **Global Notifications System**:
   - `schema.sql` was modified (via migration) so `notifications` table has a nullable `user_id` and an `alert_type` column for global alerts.
   - `admin_alerts.php` allows admins to publish and delete global campus alerts.
   - `notifications.php` allows users to view the history.
   - `header.php` automatically queries the database and displays a sticky global banner if there is an active alert.

## Next Up: The Issue Reporting System
According to the user's FYP 1 Report, the next major feature to build is the **Issue Reporting System**. 

**Requirements:**
1. **User Interface (`report_issue.php` or similar)**:
   - Users should be able to drop a pin or select a room (Node ID) on the map and submit a report (e.g., "Door is locked", "Path blocked by construction").
   - Needs a form to select issue type and write a description.
2. **Database (`reports` table)**:
   - The `reports` table already exists in `schema.sql`. It has `report_id`, `user_id`, `node_id`, `issue_type`, `description`, `status` (pending, reviewed, resolved).
   - *Note*: If guests can report issues, `user_id` in the `reports` table might need to be made nullable (similar to what we did for notifications).
3. **Admin Dashboard (`admin_reports.php`)**:
   - Admins need a view to see all incoming reports.
   - Admins should be able to update the status (e.g., from 'pending' to 'resolved').
   - Admins could potentially link this to the Notifications system (e.g., if a path is blocked, the admin can click "Create Global Alert from this report").

**Instructions for the AI:**
Please review this document and ask the user if they are ready to begin work on the **Issue Reporting System**. You have full access to read the rest of the `.php` files to understand the current architecture. Good luck!
