# Smart College Management Portal

A modern, responsive, role-based college portal built with **PHP**, **MySQL / MariaDB**, **Bootstrap 5**, and **Chart.js** for Windows XAMPP.

This platform provides a centralized academic experience with a **shared relational database** serving three distinct roles:
1. **Admin Portal**: Department, class, subject management, faculty and student registrations.
2. **Teacher Portal**: Attendance session recording, study notes & PDF uploads, assignments management, grading, and answering student doubts.
3. **Student Portal**: Attendance tracking with visual charts, class notes download, assignment submission, and posting academic doubts.

---

## 🛠️ Technology Stack

- **Backend**: Native PHP (8.x / 7.4+ compatible, PDO, Prepared Statements)
- **Database**: MySQL / MariaDB (InnoDB, relational Foreign Keys, utf8mb4)
- **Frontend**: HTML5, CSS3, Bootstrap 5.3 (via CDN), Bootstrap Icons
- **Visualizations**: Chart.js 4.4 (for attendance and academic analytics)
- **Environment**: Windows + XAMPP (Apache & MySQL)
- *Zero Composer, zero Node.js/npm, zero frameworks required.*

---

## 📁 Project File Structure

```
C:\xampp_new\htdocs\smart-clg-portal\
│
├── config\
│   └── database.php          # Secure PDO database connection & diagnostics
│
├── includes\
│   ├── auth.php              # Session management, CSRF tokens & role authorization helpers
│   ├── header.php            # HTML head, Bootstrap 5 CDN, navigation bar & user menu
│   ├── sidebar.php           # Role-aware navigation sidebar (Admin, Teacher, Student)
│   └── footer.php            # Footer, Bootstrap JS bundle, Chart.js CDN
│
├── modules\
│   ├── admin\
│   │   ├── dashboard.php     # Admin management center (role protected: requireRole('admin'))
│   │   ├── departments.php   # Departments CRUD (Add, Edit, Delete branches)
│   │   ├── classes.php       # Classes & sections CRUD with department filters
│   │   ├── subjects.php      # Curriculum subjects catalog & course management
│   │   ├── teachers.php      # Faculty directory, user registration & allocations
│   │   └── students.php      # Student admissions, roll numbering & enrollments
│   ├── teacher\
│   │   ├── dashboard.php     # Faculty dashboard & schedule (role protected: requireRole('teacher'))
│   │   ├── attendance.php    # Lecture attendance register & Chart.js turnout reports
│   │   ├── notes.php         # Study notes & lecture slides uploader
│   │   ├── assignments.php   # Coursework tasks creation & student grading
│   │   └── doubts.php        # Student doubt review & reply panel
│   └── student\
│       ├── dashboard.php     # Student dashboard & attendance summary (role protected: requireRole('student'))
│       ├── attendance.php    # Student attendance analytics & session-by-session history
│       ├── notes.php         # Class notes & syllabus materials download center
│       ├── assignments.php   # Coursework submissions & teacher feedback viewer
│       └── doubts.php        # Ask a doubt & view teacher responses
│
├── uploads\
│   ├── notes\                # Uploaded lecture PDFs, DOCs and slides
│   ├── assignments\          # Uploaded problem statement attachments
│   └── submissions\          # Uploaded student homework solutions
│
├── assets\
│   └── css\
│       └── style.css         # Custom styling for dashboard, cards & tables
│
├── database.sql              # Relational database schema (15 tables) + sample data
├── index.php                 # System overview, live status check & Chart.js graph
├── login.php                 # Secure login page with CSRF validation & demo credential fillers
├── logout.php                # Session termination & cookie cleanup handler
└── README.md                 # Setup instructions & documentation
```

---

## 🚀 Setup Instructions (Step-by-Step)

### Step 1: Start XAMPP Control Panel
1. Launch the **XAMPP Control Panel** on Windows.
2. Click the **Start** button next to **Apache** (the module name turns green).
3. Click the **Start** button next to **MySQL** (the module name turns green on default port `3306`).

---

### Step 2: Import `database.sql` Using phpMyAdmin

1. Open your web browser (Chrome, Edge, Firefox, etc.) and go to:
   ```
   http://localhost/phpmyadmin/
   ```
2. In the top navigation bar of phpMyAdmin, click the **Import** tab.
3. Under **File to import**, click **Choose File** (or *Browse*).
4. Navigate to your project folder and select `database.sql`:
   ```
   C:\xampp_new\htdocs\smart-clg-portal\database.sql
   ```
5. Leave the default character set as **utf-8** and format as **SQL**.
6. Scroll to the bottom of the page and click the **Import** (or **Go**) button.
7. phpMyAdmin will display a success banner:
   > *"Import has been successfully finished, queries executed."*
8. The database `smart_clg_portal` will now appear in your left sidebar with all **15 relational tables** and synthetic sample data.

---

### Step 3: Test the Application

1. In your browser, navigate to:
   ```
   http://localhost/smart-clg-portal/
   ```
2. You will see the **Smart College Management Portal** landing dashboard.
3. Confirm the following visual checks:
   - **Status Badge (Top right)**: Displays `MySQL: Connected` in green.
   - **Live Metrics**: Shows counts of Users (6), Departments (3), Classes & Subjects (3/4), Attendance Sessions (3).
   - **Chart.js Attendance Graph**: An interactive doughnut graph rendering attendance records (Present, Late, Absent).
   - **Synthetic Sample Accounts Table**: Lists pre-configured demo users.

---

## 🔑 Pre-Seeded Synthetic Sample Accounts

All demo accounts are created in `database.sql` with passwords securely hashed using PHP `password_hash()` (Bcrypt):

| Role | Username | Email | Demo Password | Purpose |
| :--- | :--- | :--- | :--- | :--- |
| **Admin** | `admin` | `admin@smartcollege.edu` | `Admin@123` | Master control & academic configuration |
| **Teacher** | `teacher_sharma` | `r.sharma@smartcollege.edu` | `Teacher@123` | DBMS & Web Tech teacher |
| **Teacher** | `teacher_verma` | `p.verma@smartcollege.edu` | `Teacher@123` | Operating Systems teacher |
| **Student** | `student_aarav` | `aarav.patel@student.smartcollege.edu` | `Student@123` | CSE 5th Sem student (Roll: CSE-25-001) |
| **Student** | `student_diya` | `diya.sharma@student.smartcollege.edu` | `Student@123` | CSE 5th Sem student (Roll: CSE-25-002) |
| **Student** | `student_rohan` | `rohan.mehta@student.smartcollege.edu` | `Student@123` | CSE 5th Sem student (Roll: CSE-25-003) |

---

## 🗄️ Relational Database Schema (14 Tables)

The database schema `smart_clg_portal` enforces relational integrity using **InnoDB** foreign keys:

1. `users` — Shared credentials, email, password hash, role (`admin`, `teacher`, `student`), and status.
2. `departments` — Academic departments (`CSE`, `IT`, `ECE`).
3. `classes` — Class sections, semester numbers, and academic year (`CSE-5A`, 5th Sem, 2025-2026).
4. `subjects` — Course curriculum subjects linked to departments (`CS501`, `CS502`, etc.).
5. `student_profiles` — 1-to-1 extension of `users` storing roll numbers, enrollments, class ID, and guardians.
6. `teacher_profiles` — 1-to-1 extension of `users` storing employee IDs, designations, and qualifications.
7. `teacher_assignments` — Maps teachers to specific subjects and classes per academic year.
8. `attendance_sessions` — Class lecture sessions with date, time, and lecture topic.
9. `attendance_records` — Individual student status (`Present`, `Absent`, `Late`, `Excused`) per session.
10. `notes` — Study materials, uploaded PDF paths, and lecture slides.
11. `assignments` — Coursework tasks, descriptions, deadlines, and maximum marks.
12. `assignment_submissions` — Student assignment files, submission timestamps, marks, and feedback.
13. `doubts` — Academic question queries posted by students to course teachers.
14. `doubt_responses` — Teacher solutions and answers posted to doubts.

---

## ⚙️ Database Configuration Details

The database connection is defined in [`config/database.php`](file:///c:/xampp_new/htdocs/smart-clg-portal/config/database.php):

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'smart_clg_portal');
define('DB_USER', 'root');
define('DB_PASS', ''); // Default empty password for XAMPP MySQL
```

If your XAMPP MySQL uses a different port or password, edit those constants in `config/database.php`.

---

## 🔧 Troubleshooting

- **Error: "Unable to connect to database smart_clg_portal"**:
  - Ensure the **MySQL** service is started in the XAMPP Control Panel.
  - Verify that you imported `database.sql` in phpMyAdmin so the database `smart_clg_portal` exists.
- **Port 80 / 443 conflict in Apache**:
  - If Apache does not start due to port 80 conflicts (e.g., Skype or IIS), open XAMPP Control Panel &rarr; *Config* &rarr; *httpd.conf* and change `Listen 80` to `Listen 8080`, then access `http://localhost:8080/smart-clg-portal/`.
- **phpMyAdmin upload size limit**:
  - `database.sql` is approximately 14 KB, well below the default 2 MB/40 MB phpMyAdmin limit.

---

## 🔐 Authentication & Role-Based Authorization (Stage 2)

### Accessing the Portals
1. Navigate to:
   ```
   http://localhost/smart-clg-portal/login.php
   ```
2. You can click any of the **Quick Demo Fill** buttons on the sign-in form to instantly populate demo credentials:
   - **Admin**: `admin` / `Admin@123` &rarr; redirects to `modules/admin/dashboard.php`
   - **Teacher**: `teacher_sharma` / `Teacher@123` &rarr; redirects to `modules/teacher/dashboard.php`
   - **Student**: `student_aarav` / `Student@123` &rarr; redirects to `modules/student/dashboard.php`
3. If an unauthenticated user or unauthorized role tries to access a protected dashboard directly, they are denied access and redirected to `login.php` with a flash alert.
4. Click **Sign Out** anytime in the top-right user menu to clear the session securely.

---

## 🗺️ Roadmap & Project Stages

- [x] **Stage 1**: Relational Database Schema (`database.sql`), PDO Configuration, Modular Layout (`header`, `sidebar`, `footer`), Base Styling, and System Overview.
- [x] **Stage 2**: Authentication & Server-Side Authorization (`login.php`, `logout.php`, `includes/auth.php`, and role dashboards for Admin, Teacher, and Student).
- [x] **Stage 3**: Administrator Portal CRUD (Department management, Class sections, Subjects master, Faculty & Student registration forms).
- [x] **Stage 4**: Teacher Attendance Module (Lecture session creator, one-click present/absent/late register, attendance percentage reports).
- [x] **Stage 5**: Academic Resource Center (PDF study notes upload & download, assignments creation, student submission uploads, and grading).
- [x] **Stage 6**: Doubts discussion forum — students post academic questions, teachers respond. *(Project complete at this stage.)*
