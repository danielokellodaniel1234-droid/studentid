# Student ID System folders

The application pages are grouped by role:

- `admin/` contains the administrator dashboard and staff dashboard.
- `students/` contains the student dashboard, shared student navigation, student portal workflows, and registration pages.
- `shared/` contains login, authentication processing, logout, and the local QR SVG generator.
- `assets/student-panel.css` and `assets/student-panel.js` provide the consistent student top bar, hamburger menu, and portal layout.
- `admin/faculties_courses.php` lets administrators maintain the faculty/course catalog and upload the Academic Registrar signature used by the student card.
- `database/migrate.php` creates the additive student service-request, card-catalog, and card-detail fields.

Root-level routes such as `dashboard.php`, `admin_dashboard.php`, `student_dashboard.php`, `student_portal.php`, `login.php`, and `register.php` remain as compatibility entry points for existing links and bookmarks. Shared configuration, database helpers, and common header/footer files remain in the root because they are used across roles.

The student dashboard and every student portal page use the same top bar and hamburger navigation. Student ID applications, profile/password updates, tracking, and help/service inquiries are available inside the portal. University service links submit inquiries only; no external course, payment, library, hostel, or results system is connected.

Administrators can open **Card Faculties, Courses & Signature** from the admin sidebar or visit `http://localhost/STUDENTID/admin_faculties_courses.php`. Add active faculties and their courses/programmes and upload the Academic Registrar signature before students submit applications.

## Administrator sign-in

Open `http://localhost/STUDENTID/login.php`, select **Administrator**, and sign in with an active account from the `staff_accounts` database table. Administrators are not student records; login now checks the selected account's actual staff role and password.
