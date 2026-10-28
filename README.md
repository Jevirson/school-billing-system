<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## School billing account access

New registrations receive the **Student** role automatically. They choose a unique username, and sign in with that username. Users cannot choose or change their own role. After registering an account at `/register`, grant administrator access from the project directory:

```powershell
php artisan school:make-admin admin@example.com
```

Replace `admin@example.com` with the email address used to register. Sign in with that account and visit `/dashboard`; administrators are sent to the admin dashboard and can manage roles from **Manage users**. Do not demote the last administrator. Students are sent to their student dashboard and cannot access admin routes. Both roles can edit their own profile and upload or remove a JPG, JPEG, or PNG profile photo up to 40 MB using **Profile** in the account menu; the photo is shown in the signed-in header and automatically fitted to the avatar.

To create a separate initial administrator, run `php artisan school:create-admin admin admin@school.test` and enter a password at the hidden prompt. After authentication, administrators land directly on the admin dashboard, while students land on the student dashboard. Administrators can grant another account admin access in **Manage users**.

## Billing workflow

The billing module uses the configured application database. Apply its schema migrations from the project directory with:

```powershell
php artisan migrate --force
```

In **Billing setup**, an administrator can add courses, course subjects, school years and semesters, fee types, fixed-peso scholarships, and dates. **Exam dates** are separate from **Payment due dates**: setting or announcing an exam does not change a bill's due date, and payment due-date notices do not announce exam dates. Payment schedules update matching existing bills and apply automatically to future bills. Deleting a payment schedule leaves existing bills' saved due dates intact. Subjects include academic units and optional year/semester targeting; subject units do not add a separate fee to bills. Use **Enrollment** to assign multiple applicable subjects to each student before billing; students are not enrolled automatically. When creating a bill, select the student's unbilled subjects to link them to the bill and select applicable catalog fees for charges. A bill snapshots its fee lines and scholarship amount so later catalog edits do not change invoices already issued. The system prevents duplicate student/term/period bills, caps the applied scholarship discount at the bill's total fees, and rejects payments above the outstanding balance.

Administrators can send school-wide messages from **Announcements** in the admin navigation or dashboard. Each announcement is delivered to all current student accounts and appears in their dashboard notification area and notification history.

Students can review their enrolled subjects under **My subjects** and request to withdraw from a subject. Administrators review these requests under **Withdrawal requests**. Approval removes a matching legacy subject charge from the current bill; any resulting overpayment is recorded as a cash refund due, and an administrator records it as paid after issuing the refund. Existing payment history is retained.

Administrators have create/read/update/delete controls for courses, terms, fees, scholarships, bills, and administrator accounts. Catalog records cannot be deleted while referenced by students, bills, or carry-forwards. Bills may be edited or deleted only before any payment or promissory history exists; administrator accounts linked to payment or promissory audit records are retained. Student deletion requires typing the exact student ID for final confirmation and permanently removes the student's login, invoices, payments, payment correction history, promissory letters, and carry-forwards. The bills list can be searched by invoice, student, or course and filtered by term and payment status. Students can view their own unpaid, partially paid, and paid bills with recorded payment details in student-only payment history. Administrators record and correct payments from each bill; every correction records the previous and new values and the editor in an audit history.

Each bill shows the latest payment separately from the total of all payments. Balances include any approved carry-forward into the payment period and exclude amounts moved to a later period. Invoices include the student's formatted name, ID, course, year level, academic term, itemized fees, discount, payment details, status, and balance. Students can view only their own bills and print their own invoices from **My bills**.

Students can open and print a **Statement of Account** for each bill and view their **Payment history** from the student navigation. No online payment gateway is configured, so students submit a full or partial payment request (after confirming the request) rather than being shown a false successful payment. A pending request does not affect the balance; an administrator must verify receipt before it becomes a payment transaction. Rejected requests remain in history and may be resubmitted. Administrators can review, approve, or reject requests from **Payment requests**; approval records the payment under the reviewing administrator and notifies the student. Administrators can also record direct office payments without a student request. Verified student payments and office payments both appear in the student's bill, history, and receipt.

The student dashboard shows a tuition installment guide based on ₱21,000 tuition less the active enrollment and sanitation fee amounts in the fee catalog, divided across Prelim, Midterm, Semi-final, and Final. This guide does not modify invoices; the student's actual balance is determined by billed charges and recorded payments.

### Student scholarships and promissory letters

Administrators can assign up to two different fixed-peso scholarships to a student from the student create/edit form. Their discounts are added together and capped at the bill's total fees. The assigned discounts are applied server-side when a new bill is created; the bill saves a snapshot of the discount and scholarship names, so a later scholarship change does not alter an existing invoice.

The data model uses `student_profiles` for the diagram's student record, `bill_items` for its bill-detail rows, `fee_types` for reusable fee definitions, and term-specific `enrollments` to track subjects and units linked to bills. Each bill has one student, school term, and billing period, and has multiple item and payment records.

Students can submit a promissory-letter request from an unpaid or partially paid bill using a formatted letter preview, print the letter, and track its review status. The letter captures the current balance, requested partial or full deferral, and proposed next payment period. Administrators can approve all or part of the requested amount or reject it with a reason. Approval carries the amount to the immediately following payment period in the same school term (Prelim → Midterm → Semi-final → Final), whether or not that period's payment due date has been configured. Exam dates are separate and do not determine promissory destinations. Approval creates or links the destination-period invoice, preserves the original invoice and decision history, and carries the approved unpaid amount; it does not create a payment transaction. Payments against the destination invoice reduce the carry-forward, and any new fees remain itemized separately. Run Laravel's scheduler daily in production so approved commitments are marked overdue and administrators are notified:

```powershell
php artisan schedule:work
```

Approved arrears are due in the **immediately following payment period** and remain linked to the original invoice for auditing. If the destination-period bill does not exist, approval creates a carry-forward-only invoice; administrators can add that period's new fees to the same invoice later. Destination-period payments reduce the linked carried amount, while the original invoice remains in history and its current-period balance is reduced by the approved deferral. Pending and rejected requests do not change balances. A fully paid bill cannot be carried forward.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
