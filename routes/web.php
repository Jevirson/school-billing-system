<?php

use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\BillingController as AdminBillingController;
use App\Http\Controllers\Admin\BillingScheduleController;
use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\EnrollmentController;
use App\Http\Controllers\Admin\ExamScheduleController;
use App\Http\Controllers\Admin\PaymentRequestController as AdminPaymentRequestController;
use App\Http\Controllers\Admin\PromissoryLetterController as AdminPromissoryLetterController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\SubjectWithdrawalController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Student\BillingController as StudentBillingController;
use App\Http\Controllers\Student\EnrollmentController as StudentEnrollmentController;
use App\Http\Controllers\Student\PromissoryLetterController as StudentPromissoryLetterController;
use App\Models\Bill;
use App\Models\BillExtension;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\PromissoryLetter;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\TuitionInstallmentPlan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return auth()->user()->isAdmin()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('student.dashboard');
    })->name('dashboard');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::get('/student/enrollment', [StudentEnrollmentController::class, 'index'])
        ->middleware('can:student')->name('student.enrollment.index');
    Route::post('/student/enrollment/{enrollment}/withdrawal-requests', [StudentEnrollmentController::class, 'requestWithdrawal'])
        ->middleware('can:student')->name('student.enrollment.withdrawals.store');

    Route::get('/student/dashboard', function () {
        $student = auth()->user()->studentProfile;

        return view('dashboards.student', [
            'studentProfile' => $student?->load([
                'course',
                'scholarship',
                'scholarships',
                'enrollments' => fn ($query) => $query->whereNull('withdrawn_at')->with(['subject', 'schoolTerm']),
            ]),
            'studentBills' => $student
                ? $student->bills()->with(['schoolTerm', 'payments', 'latestPayment', 'paymentRequests', 'promissoryLetters', 'extensions', 'extensionsReceived.sourceBill', 'extensionsReceived.promissoryLetter'])->latest('billing_date')->get()
                : collect(),
            'studentCarryForwards' => $student
                ? BillExtension::with(['sourceBill.schoolTerm', 'sourceBill.payments', 'nextSchoolTerm', 'destinationBill'])
                    ->whereHas('sourceBill', fn ($query) => $query->where('student_profile_id', $student->id))
                    ->orderByDesc('created_at')
                    ->get()
                : collect(),
            'billingNotifications' => auth()->user()->unreadNotifications->take(5),
            'upcomingPaymentBills' => $student
                ? $student->bills()
                    ->with(['schoolTerm', 'payments', 'extensions', 'extensionsReceived'])
                    ->whereNotNull('due_date')
                    ->get()
                    ->filter(fn (Bill $bill): bool => (float) $bill->period_balance > 0)
                    ->sortBy('due_date')
                    ->take(5)
                : collect(),
            'pendingPaymentRequestCount' => $student?->paymentRequests()->where('status', 'Pending')->count() ?? 0,
            'tuitionPlan' => TuitionInstallmentPlan::calculate(),
        ]);
    })->middleware('can:student')->name('student.dashboard');

    Route::get('/student/bills', [StudentBillingController::class, 'index'])
        ->middleware('can:student')->name('student.bills.index');
    Route::get('/student/bills/{bill}/invoice', [StudentBillingController::class, 'invoice'])
        ->middleware('can:student')->name('student.bills.invoice');
    Route::get('/student/bills/{bill}/statement', [StudentBillingController::class, 'statement'])
        ->middleware('can:student')->name('student.bills.statement');
    Route::post('/student/bills/{bill}/payment-requests', [StudentBillingController::class, 'submitPaymentRequest'])
        ->middleware('can:student')->name('student.bills.payments.store');
    Route::get('/student/payment-history', [StudentBillingController::class, 'paymentHistory'])
        ->middleware('can:student')->name('student.payments.index');
    Route::get('/student/payments/{payment}/receipt', [StudentBillingController::class, 'receipt'])
        ->middleware('can:student')->name('student.payments.receipt');
    Route::get('/student/promissory-letters', [StudentPromissoryLetterController::class, 'index'])
        ->middleware('can:student')->name('student.promissory-letters.index');
    Route::get('/student/bills/{bill}/promissory-letter/create', [StudentPromissoryLetterController::class, 'create'])
        ->middleware('can:student')->name('student.promissory-letters.create');
    Route::post('/student/promissory-letters', [StudentPromissoryLetterController::class, 'store'])
        ->middleware('can:student')->name('student.promissory-letters.store');
    Route::get('/student/promissory-letters/{letter}', [StudentPromissoryLetterController::class, 'show'])
        ->middleware('can:student')->name('student.promissory-letters.show');

    Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('/payment-requests', [AdminPaymentRequestController::class, 'index'])->name('payment-requests.index');
        Route::post('/payment-requests/{paymentRequest}/decision', [AdminPaymentRequestController::class, 'decide'])->name('payment-requests.decide');
        Route::get('/subject-withdrawals', [SubjectWithdrawalController::class, 'index'])->name('subject-withdrawals.index');
        Route::post('/subject-withdrawals/{withdrawalRequest}/decision', [SubjectWithdrawalController::class, 'decide'])->name('subject-withdrawals.decide');
        Route::post('/subject-withdrawal-refunds/{refund}/paid', [SubjectWithdrawalController::class, 'recordRefund'])->name('subject-withdrawals.refunds.paid');
        Route::get('/billing-schedules', [BillingScheduleController::class, 'index'])->name('billing-schedules.index');
        Route::post('/billing-schedules', [BillingScheduleController::class, 'store'])->name('billing-schedules.store');
        Route::put('/billing-schedules/{billingSchedule}', [BillingScheduleController::class, 'update'])->name('billing-schedules.update');
        Route::delete('/billing-schedules/{billingSchedule}', [BillingScheduleController::class, 'destroy'])->name('billing-schedules.destroy');
        Route::get('/exam-schedules', [ExamScheduleController::class, 'index'])->name('exam-schedules.index');
        Route::post('/exam-schedules', [ExamScheduleController::class, 'store'])->name('exam-schedules.store');
        Route::put('/exam-schedules/{examSchedule}', [ExamScheduleController::class, 'update'])->name('exam-schedules.update');
        Route::delete('/exam-schedules/{examSchedule}', [ExamScheduleController::class, 'destroy'])->name('exam-schedules.destroy');
        Route::get('/dashboard', function () {
            return view('dashboards.admin', [
                'totalUsers' => User::count(),
                'studentCount' => User::where('role', User::ROLE_STUDENT)->count(),
                'adminCount' => User::where('role', User::ROLE_ADMIN)->count(),
                'profileCount' => StudentProfile::count(),
                'billCount' => Bill::count(),
                'pendingPaymentRequestCount' => PaymentRequest::where('status', 'Pending')->count(),
                'outstandingBalance' => number_format(
                    max(0, (float) Bill::sum('net_amount_due') - (float) Payment::sum('amount')),
                    2,
                    '.',
                    ''
                ),
                'recentBills' => Bill::with(['studentProfile', 'schoolTerm', 'payments', 'latestPayment'])
                    ->latest('billing_date')
                    ->latest('id')
                    ->limit(5)
                    ->get(),
                'pendingPromissoryCount' => PromissoryLetter::where('status', 'Pending')->count(),
            ]);
        })->name('dashboard');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
        Route::post('/subjects', [CatalogController::class, 'storeSubject'])->name('subjects.store');
        Route::put('/subjects/{subject}', [CatalogController::class, 'updateSubject'])->name('subjects.update');
        Route::delete('/subjects/{subject}', [CatalogController::class, 'destroySubject'])->name('subjects.destroy');
        Route::get('/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
        Route::get('/enrollments/create', [EnrollmentController::class, 'create'])->name('enrollments.create');
        Route::get('/enrollments/{studentProfile}', [EnrollmentController::class, 'show'])->name('enrollments.show');
        Route::post('/enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
        Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('enrollments.destroy');
        Route::post('/courses', [CatalogController::class, 'storeCourse'])->name('courses.store');
        Route::put('/courses/{course}', [CatalogController::class, 'updateCourse'])->name('courses.update');
        Route::delete('/courses/{course}', [CatalogController::class, 'destroyCourse'])->name('courses.destroy');
        Route::post('/school-terms', [CatalogController::class, 'storeTerm'])->name('terms.store');
        Route::put('/school-terms/{term}', [CatalogController::class, 'updateTerm'])->name('terms.update');
        Route::delete('/school-terms/{term}', [CatalogController::class, 'destroyTerm'])->name('terms.destroy');
        Route::post('/fees', [CatalogController::class, 'storeFee'])->name('fees.store');
        Route::put('/fees/{fee}', [CatalogController::class, 'updateFee'])->name('fees.update');
        Route::delete('/fees/{fee}', [CatalogController::class, 'destroyFee'])->name('fees.destroy');
        Route::post('/scholarships', [CatalogController::class, 'storeScholarship'])->name('scholarships.store');
        Route::put('/scholarships/{scholarship}', [CatalogController::class, 'updateScholarship'])->name('scholarships.update');
        Route::delete('/scholarships/{scholarship}', [CatalogController::class, 'destroyScholarship'])->name('scholarships.destroy');

        Route::get('/students', [StudentController::class, 'index'])->name('students.index');
        Route::get('/students/create', [StudentController::class, 'create'])->name('students.create');
        Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
        Route::post('/students', [StudentController::class, 'store'])->name('students.store');
        Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
        Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');

        Route::get('/bills', [AdminBillingController::class, 'index'])->name('bills.index');
        Route::get('/bills/create', [AdminBillingController::class, 'create'])->name('bills.create');
        Route::post('/bills', [AdminBillingController::class, 'store'])->name('bills.store');
        Route::get('/bills/{bill}/edit', [AdminBillingController::class, 'edit'])->name('bills.edit');
        Route::put('/bills/{bill}', [AdminBillingController::class, 'update'])->name('bills.update');
        Route::delete('/bills/{bill}', [AdminBillingController::class, 'destroy'])->name('bills.destroy');
        Route::get('/bills/{bill}', [AdminBillingController::class, 'show'])->name('bills.show');
        Route::get('/bills/{bill}/invoice', [AdminBillingController::class, 'invoice'])->name('bills.invoice');
        Route::get('/bills/{bill}/statement', [AdminBillingController::class, 'statement'])->name('bills.statement');
        Route::post('/bills/{bill}/payments', [AdminBillingController::class, 'recordPayment'])->name('bills.payments.store');
        Route::get('/payments/{payment}/edit', [AdminBillingController::class, 'editPayment'])->name('payments.edit');
        Route::get('/payments/{payment}/receipt', [AdminBillingController::class, 'receipt'])->name('payments.receipt');
        Route::put('/payments/{payment}', [AdminBillingController::class, 'updatePayment'])->name('payments.update');
        Route::get('/promissory-letters', [AdminPromissoryLetterController::class, 'index'])->name('promissory-letters.index');
        Route::get('/promissory-letters/{letter}', [AdminPromissoryLetterController::class, 'show'])->name('promissory-letters.show');
        Route::post('/promissory-letters/{letter}/decision', [AdminPromissoryLetterController::class, 'decide'])->name('promissory-letters.decide');
    });
});
