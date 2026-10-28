<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\BillExtension;
use App\Models\BillingSchedule;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ExamSchedule;
use App\Models\FeeType;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\PromissoryLetter;
use App\Models\Scholarship;
use App\Models\SchoolTerm;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use App\Notifications\PaymentRecordedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_bill_with_fixed_scholarship_and_post_payment(): void
    {
        [$admin, $student, $profile, $term, $fees, $scholarship] = $this->billingSetup();
        $forgedScholarship = Scholarship::create([
            'name' => 'Forged Scholarship',
            'discount_amount' => '10000.00',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.bills.store'), [
                'student_profile_id' => $profile->id,
                'school_term_id' => $term->id,
                'fee_ids' => $fees->pluck('id')->all(),
                'billing_date' => '2026-10-01',
                'scholarship_id' => $forgedScholarship->id,
            ])
            ->assertRedirect();

        $bill = Bill::firstOrFail();
        $this->assertSame('25000.00', $bill->total_fees);
        $this->assertSame($scholarship->id, $bill->scholarship_id);
        $this->assertSame('5000.00', $bill->scholarship_discount);
        $this->assertSame('20000.00', $bill->net_amount_due);
        $this->assertTrue($scholarship->studentProfiles->contains($profile));
        $scholarship->update(['discount_amount' => '9000.00']);
        $this->assertSame('5000.00', $bill->fresh()->scholarship_discount);

        $this->actingAs($admin)
            ->post(route('admin.bills.payments.store', $bill), [
                'amount' => '5000.00',
                'paid_at' => '2026-10-11T12:00',
                'method' => 'Cash',
            ])
            ->assertRedirect(route('admin.bills.show', $bill));

        $bill->refresh();
        $this->assertSame('5000.00', $bill->total_paid);
        $this->assertSame('15000.00', $bill->remaining_balance);
        $this->assertSame('Partially Paid', $bill->payment_status);
        $this->assertSame('5000.00', $bill->latestPayment->amount);
        $payment = $bill->payments()->firstOrFail();
        $this->assertSame('15000.00', $payment->balance_after);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $student->id]);

        $this->actingAs($admin)->get(route('admin.bills.show', $bill))
            ->assertOk()
            ->assertSee('Record Office Payment')
            ->assertSee(route('admin.payments.receipt', $payment), false);
        $this->actingAs($admin)->get(route('admin.payments.receipt', $payment))
            ->assertOk()
            ->assertSee('OFFICIAL PAYMENT RECEIPT')
            ->assertSee($profile->student_number)
            ->assertSee('₱15,000.00');
        $this->actingAs($student)->get(route('student.payments.receipt', $payment))
            ->assertOk()
            ->assertSee('OFFICIAL PAYMENT RECEIPT');

        $this->actingAs($admin)
            ->get(route('admin.bills.invoice', $bill))
            ->assertOk()
            ->assertSee('Latest payment')
            ->assertSee('₱5,000.00')
            ->assertSee('₱15,000.00');
    }

    public function test_notification_center_shows_payment_updates_and_due_dates_for_existing_and_new_students(): void
    {
        [$admin, $existingStudent, $existingProfile, $term] = $this->billingSetup();
        $existingBill = $this->createBill($existingProfile, $term);
        $existingBill->update(['due_date' => today()->addDays(3)]);
        $payment = Payment::create([
            'bill_id' => $existingBill->id,
            'recorded_by' => $admin->id,
            'amount' => '1000.00',
            'paid_at' => now(),
            'method' => 'Office Payment',
        ]);
        $existingStudent->notify(new PaymentRecordedNotification($payment));

        $newProfile = $this->createStudentProfile();
        $newBill = $this->createBill($newProfile, $term);
        $newBill->update([
            'invoice_number' => 'INV-NEW-STUDENT',
            'due_date' => today()->addDays(7),
        ]);

        $this->actingAs($admin)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee($existingProfile->invoice_name)
            ->assertSee($newProfile->invoice_name)
            ->assertSee('Next student payments')
            ->assertSee('id="side-navigation"', false);

        $this->actingAs($existingStudent)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Payment recorded')
            ->assertSee($existingBill->invoice_number)
            ->assertDontSee($newProfile->invoice_name);

        $this->actingAs($existingStudent)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Next payment')
            ->assertSee($existingBill->due_date->format('M j'));

        $notification = $existingStudent->notifications()->firstOrFail();
        $this->actingAs($existingStudent)
            ->post(route('notifications.open', $notification))
            ->assertRedirect(route('student.payments.index'));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_student_cannot_print_another_students_payment_receipt(): void
    {
        [, $student, , $term] = $this->billingSetup();
        $otherProfile = $this->createStudentProfile();
        $otherBill = $this->createBill($otherProfile, $term);
        $payment = Payment::create([
            'bill_id' => $otherBill->id,
            'recorded_by' => User::where('role', User::ROLE_ADMIN)->firstOrFail()->id,
            'amount' => '1000.00',
            'paid_at' => now(),
            'method' => 'Office Payment',
        ]);

        $this->actingAs($student)->get(route('student.payments.receipt', $payment))->assertForbidden();
    }

    public function test_admin_caps_scholarship_discount_at_total_fees_and_prevents_duplicate_bill(): void
    {
        [$admin, , $profile, $term, $fees, $scholarship] = $this->billingSetup(discount: '26000.00');
        $payload = [
            'student_profile_id' => $profile->id,
            'school_term_id' => $term->id,
            'fee_ids' => $fees->pluck('id')->all(),
            'billing_date' => '2026-10-01',
        ];

        $this->actingAs($admin)->post(route('admin.bills.store'), $payload)->assertRedirect();

        $bill = Bill::firstOrFail();
        $this->assertSame('25000.00', $bill->total_fees);
        $this->assertSame('25000.00', $bill->scholarship_discount);
        $this->assertSame('0.00', $bill->net_amount_due);
        $this->assertSame($scholarship->id, $bill->scholarship_id);

        $scholarship->update(['discount_amount' => '5000.00']);
        $this->actingAs($admin)->post(route('admin.bills.store'), $payload)->assertRedirect();
        $this->actingAs($admin)
            ->from(route('admin.bills.create'))
            ->post(route('admin.bills.store'), $payload)
            ->assertSessionHasErrors('school_term_id');

        $this->assertCount(1, Bill::all());
    }

    public function test_two_student_scholarships_are_added_and_capped_at_bill_fees(): void
    {
        [$admin, , $profile, $term, $fees, $firstScholarship] = $this->billingSetup(discount: '18000.00');
        $secondScholarship = Scholarship::create([
            'name' => 'Community Grant',
            'discount_amount' => '10000.00',
        ]);
        $profile->scholarships()->attach($secondScholarship);

        $this->actingAs($admin)->post(route('admin.bills.store'), [
            'student_profile_id' => $profile->id,
            'school_term_id' => $term->id,
            'fee_ids' => $fees->pluck('id')->all(),
            'billing_date' => '2026-10-01',
        ])->assertRedirect();

        $bill = Bill::firstOrFail();
        $this->assertSame('25000.00', $bill->total_fees);
        $this->assertSame('25000.00', $bill->scholarship_discount);
        $this->assertSame('0.00', $bill->net_amount_due);
        $this->assertSame($firstScholarship->name.', '.$secondScholarship->name, $bill->scholarship_name);
    }

    public function test_admin_can_manage_subjects_enroll_students_and_link_subjects_without_charging_a_subject_fee(): void
    {
        [$admin, $student, $profile, $term, $fees] = $this->billingSetup();
        $course = Course::create(['code' => 'CS', 'name' => 'Computer Science']);
        $profile->update(['course_id' => $course->id, 'year_level' => '1st Year']);
        $subjectData = [
            'course_id' => $course->id,
            'code' => 'CS101',
            'name' => 'Introduction to Computing',
            'units' => '3.0',
            'year_level' => '1st Year',
            'semester' => $term->semester,
        ];

        $this->actingAs($admin)->post(route('admin.subjects.store'), $subjectData)->assertRedirect()->assertSessionHasNoErrors();
        $subject = Subject::firstOrFail();
        $this->actingAs($admin)->get(route('admin.catalog.index'))
            ->assertOk()
            ->assertSee('Introduction to Computing')
            ->assertDontSee('name="subject_fee"', false)
            ->assertSee('No fee');
        $this->assertFalse(Schema::hasColumn('subjects', 'subject_fee'));
        $this->assertFalse(Schema::hasColumn('enrollments', 'subject_fee'));
        $this->actingAs($admin)->get(route('admin.enrollments.create'))
            ->assertOk()
            ->assertSee('Available subjects')
            ->assertSee('Select visible')
            ->assertSee('Selected units');

        $this->actingAs($admin)->post(route('admin.enrollments.store'), [
            'student_profile_id' => $profile->id,
            'school_term_id' => $term->id,
            'subject_ids' => [$subject->id],
        ])->assertRedirect(route('admin.enrollments.index'));

        $enrollment = Enrollment::firstOrFail();
        $this->assertSame('3.0', $enrollment->units);
        $this->actingAs($admin)->get(route('admin.enrollments.index'))
            ->assertOk()
            ->assertSee($profile->invoice_name)
            ->assertSee($course->code)
            ->assertSee('1st Year')
            ->assertSee('View subjects')
            ->assertDontSee('Introduction to Computing');
        $this->actingAs($admin)->get(route('admin.enrollments.show', $profile))
            ->assertOk()
            ->assertSee('Introduction to Computing')
            ->assertSee('3.0 units');
        $this->actingAs($admin)->get(route('admin.bills.create'))
            ->assertOk()
            ->assertSee('Subjects are not charged separately.')
            ->assertSee('Selected catalog fees');
        $this->actingAs($admin)->from(route('admin.enrollments.create'))->post(route('admin.enrollments.store'), [
            'student_profile_id' => $profile->id,
            'school_term_id' => $term->id,
            'subject_ids' => [$subject->id],
        ])->assertSessionHasErrors('subject_ids');
        $this->assertDatabaseCount('enrollments', 1);

        $this->actingAs($admin)->post(route('admin.bills.store'), [
            'student_profile_id' => $profile->id,
            'school_term_id' => $term->id,
            'fee_ids' => $fees->pluck('id')->all(),
            'enrollment_ids' => [$enrollment->id],
            'billing_date' => today()->toDateString(),
        ])->assertRedirect();

        $bill = Bill::firstOrFail();
        $this->assertSame('25000.00', $bill->total_fees);
        $this->assertSame('5000.00', $bill->scholarship_discount);
        $this->assertSame('20000.00', $bill->net_amount_due);
        $this->assertSame($bill->id, $enrollment->fresh()->bill_id);
        $this->assertSame(2, $bill->items()->count());
        $this->assertDatabaseMissing('bill_items', [
            'bill_id' => $bill->id,
            'description' => 'Subject CS101 — Introduction to Computing (3.0 units)',
        ]);
        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Introduction to Computing')
            ->assertSee('No extra fee');
    }

    public function test_billing_schedule_applies_to_existing_and_future_bills_with_student_notifications(): void
    {
        [$admin, $student, $profile, $term, $fees] = $this->billingSetup();
        $existingBill = $this->createBill($profile, $term);
        $schedule = [
            'school_term_id' => $term->id,
            'billing_period' => 'Full Term',
            'due_date' => today()->subDay()->toDateString(),
        ];

        $this->actingAs($admin)->from(route('admin.catalog.index'))
            ->post(route('admin.billing-schedules.store'), $schedule)->assertRedirect(route('admin.catalog.index'));
        $existingBill->refresh();
        $this->assertSame('Full Term', $existingBill->billing_period);
        $this->assertSame(today()->subDay()->toDateString(), $existingBill->due_date->toDateString());
        $this->assertSame('Overdue', $existingBill->payment_status);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $student->id]);
        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Billing due date scheduled');

        $this->actingAs($admin)->from(route('admin.catalog.index'))->post(route('admin.billing-schedules.store'), $schedule)
            ->assertSessionHasErrors('billing_period');
        $this->assertDatabaseCount('billing_schedules', 1);

        $nextSchedule = [
            'school_term_id' => $term->id,
            'billing_period' => 'Prelim',
            'due_date' => today()->addDays(14)->toDateString(),
        ];
        $this->actingAs($admin)->from(route('admin.catalog.index'))
            ->post(route('admin.billing-schedules.store'), $nextSchedule)->assertRedirect(route('admin.catalog.index'));
        $this->actingAs($admin)->post(route('admin.bills.store'), [
            'student_profile_id' => $profile->id,
            'school_term_id' => $term->id,
            'billing_period' => 'Prelim',
            'fee_ids' => $fees->pluck('id')->all(),
            'billing_date' => today()->toDateString(),
        ])->assertRedirect();

        $prelimBill = Bill::where('billing_period', 'Prelim')->firstOrFail();
        $this->assertSame(today()->addDays(14)->toDateString(), $prelimBill->due_date->toDateString());
        $this->assertSame('billing due date scheduled', strtolower($student->unreadNotifications()->latest()->firstOrFail()->data['title']));
        $this->assertDatabaseCount('billing_schedules', 2);
    }

    public function test_billing_schedule_notifies_applicable_students_without_a_bill(): void
    {
        [$admin, , , $term] = $this->billingSetup();
        $studentProfile = $this->createStudentProfile();
        $schedule = [
            'school_term_id' => $term->id,
            'billing_period' => 'Prelim',
            'due_date' => today()->addDays(14)->toDateString(),
        ];

        $this->actingAs($admin)
            ->post(route('admin.billing-schedules.store'), $schedule)
            ->assertRedirect();

        $student = $studentProfile->user;
        $notification = $student->notifications()->firstOrFail();
        $this->assertSame('Billing due date scheduled', $notification->data['title']);
        $this->assertStringContainsString('Prelim', $notification->data['message']);
        $this->assertStringContainsString(today()->addDays(14)->format('F j, Y'), $notification->data['message']);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Billing due date scheduled')
            ->assertSee(today()->addDays(14)->format('F j, Y'));

        $this->actingAs($student)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Billing due date scheduled')
            ->assertSee($notification->data['message']);
    }

    public function test_admin_can_edit_and_delete_payment_due_dates_without_changing_saved_bill_due_dates(): void
    {
        [$admin, , $profile, $term] = $this->billingSetup();
        $bill = $this->createBill($profile, $term);
        $originalDate = today()->addDays(10);
        $updatedDate = today()->addDays(20);
        $schedule = BillingSchedule::create([
            'school_term_id' => $term->id,
            'billing_period' => 'Full Term',
            'due_date' => $originalDate,
            'scope_key' => 'all:all',
        ]);
        $bill->update([
            'billing_schedule_id' => $schedule->id,
            'due_date' => $originalDate,
        ]);

        $this->actingAs($admin)->get(route('admin.catalog.index'))
            ->assertOk()
            ->assertSee('Exam dates')
            ->assertSee('Payment due dates')
            ->assertSee('Exam announcements and dates are separate from payment due dates.');

        $this->actingAs($admin)->from(route('admin.catalog.index'))->put(route('admin.billing-schedules.update', $schedule), [
            'school_term_id' => $term->id,
            'billing_period' => 'Full Term',
            'due_date' => $updatedDate->toDateString(),
            'course_id' => null,
            'year_level' => null,
        ])->assertRedirect(route('admin.catalog.index'));
        $this->assertSame($updatedDate->toDateString(), $bill->fresh()->due_date->toDateString());

        $this->actingAs($admin)->from(route('admin.catalog.index'))
            ->delete(route('admin.billing-schedules.destroy', $schedule))->assertRedirect(route('admin.catalog.index'));
        $this->assertDatabaseMissing('billing_schedules', ['id' => $schedule->id]);
        $this->assertNull($bill->fresh()->billing_schedule_id);
        $this->assertSame($updatedDate->toDateString(), $bill->fresh()->due_date->toDateString());
    }

    public function test_exam_dates_have_separate_student_notifications_and_do_not_set_payment_due_dates(): void
    {
        [$admin, $student, , $term] = $this->billingSetup();
        $examDate = today()->addDays(14);

        $this->actingAs($admin)->from(route('admin.catalog.index'))
            ->post(route('admin.exam-schedules.store'), [
                'school_term_id' => $term->id,
                'exam_period' => 'Prelim',
                'exam_date' => $examDate->toDateString(),
            ])
            ->assertRedirect(route('admin.catalog.index'));

        $this->assertDatabaseHas('exam_schedules', [
            'school_term_id' => $term->id,
            'exam_period' => 'Prelim',
        ]);
        $examSchedule = ExamSchedule::firstOrFail();
        $this->assertSame($examDate->toDateString(), $examSchedule->exam_date->toDateString());
        $this->assertDatabaseCount('billing_schedules', 0);
        $notification = $student->notifications()->firstOrFail();
        $this->assertSame('Exam date scheduled', $notification->data['title']);
        $this->assertStringContainsString($examDate->format('F j, Y'), $notification->data['message']);

        $this->actingAs($student)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Exam date scheduled')
            ->assertSee('Prelim exam');

        $updatedDate = today()->addDays(21);
        $this->actingAs($admin)->from(route('admin.catalog.index'))
            ->put(route('admin.exam-schedules.update', $examSchedule), [
                'school_term_id' => $term->id,
                'exam_period' => 'Prelim',
                'exam_date' => $updatedDate->toDateString(),
            ])
            ->assertRedirect(route('admin.catalog.index'));
        $this->assertSame($updatedDate->toDateString(), $examSchedule->fresh()->exam_date->toDateString());
        $this->assertDatabaseCount('billing_schedules', 0);

        $this->actingAs($admin)->from(route('admin.catalog.index'))
            ->delete(route('admin.exam-schedules.destroy', $examSchedule))
            ->assertRedirect(route('admin.catalog.index'));
        $this->assertDatabaseMissing('exam_schedules', ['id' => $examSchedule->id]);
    }

    public function test_student_assignment_accepts_at_most_two_distinct_scholarships(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();
        $course = Course::create(['code' => 'BSCS', 'name' => 'Computer Science']);
        $scholarships = collect([
            Scholarship::create(['name' => 'Merit Grant', 'discount_amount' => '1000.00']),
            Scholarship::create(['name' => 'Community Grant', 'discount_amount' => '2000.00']),
            Scholarship::create(['name' => 'Leadership Grant', 'discount_amount' => '3000.00']),
        ]);
        $payload = [
            'first_name' => 'Taylor',
            'last_name' => 'Scholar',
            'course_id' => $course->id,
            'is_scholar' => '1',
            'scholarship_ids' => $scholarships->pluck('id')->all(),
            'username' => 'taylorscholar',
            'email' => 'taylor@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $this->actingAs($admin)
            ->from(route('admin.students.create'))
            ->post(route('admin.students.store'), $payload)
            ->assertSessionHasErrors('scholarship_ids');
        $this->assertDatabaseMissing('users', ['username' => 'taylorscholar']);

        $payload['scholarship_ids'] = $scholarships->take(2)->pluck('id')->all();
        $this->actingAs($admin)->post(route('admin.students.store'), $payload)->assertRedirect();
        $student = StudentProfile::where('student_number', '16300001')->firstOrFail();
        $this->assertCount(2, $student->scholarships);
        $this->assertSame($scholarships->first()->id, $student->scholarship_id);
    }

    public function test_student_can_only_view_their_own_invoice(): void
    {
        [, $student, $profile, $term] = $this->billingSetup();
        $otherProfile = $this->createStudentProfile();
        $bill = $this->createBill($profile, $term);
        $otherBill = $this->createBill($otherProfile, $term);

        $this->actingAs($student)
            ->get(route('student.bills.invoice', $bill))
            ->assertOk()
            ->assertSee('Patot, Adams G.')
            ->assertSee('2026-2027')
            ->assertSee('₱25,000.00');

        $this->actingAs($student)
            ->get(route('student.bills.invoice', $otherBill))
            ->assertForbidden();
    }

    public function test_student_payment_requests_require_admin_confirmation_before_changing_balance(): void
    {
        [$admin, $student, $profile, $term] = $this->billingSetup();
        $otherProfile = $this->createStudentProfile();
        $bill = $this->createBill($profile, $term);
        $otherBill = $this->createBill($otherProfile, $term);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Submit a full or partial payment request')
            ->assertSee('Confirm and send request')
            ->assertSee('Request promissory letter')
            ->assertSee('administrator must confirm receipt')
            ->assertSee(route('student.bills.payments.store', $bill), false)
            ->assertSee(route('student.promissory-letters.create', $bill), false);

        $this->actingAs($student)->post(route('student.bills.payments.store', $otherBill), [
            'amount' => '100.00',
        ])->assertForbidden();

        $this->actingAs($student)->post(route('student.bills.payments.store', $bill), [
            'amount' => '5000.00',
            'reference' => 'REQUEST-001',
        ])->assertRedirect(route('student.dashboard'));

        $paymentRequest = PaymentRequest::where('reference', 'REQUEST-001')->firstOrFail();
        $this->assertSame($profile->id, $paymentRequest->student_profile_id);
        $this->assertSame('Pending', $paymentRequest->status);
        $this->assertSame('0.00', $bill->fresh()->total_paid);
        $this->assertSame('20000.00', $bill->fresh()->remaining_balance);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'type' => \App\Notifications\PaymentRequestSubmittedNotification::class,
        ]);
        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Payment request pending: ₱5,000.00');

        $this->actingAs($student)->from(route('student.dashboard'))
            ->post(route('student.bills.payments.store', $bill), [
                'amount' => '15000.01',
            ])
            ->assertSessionHasErrors('amount');
        $this->actingAs($student)->from(route('student.dashboard'))
            ->post(route('student.bills.payments.store', $bill), [
                'amount' => '100.00',
            ])
            ->assertSessionHasErrors('amount');
        $this->assertSame(0, $bill->payments()->count());
        $this->assertSame(1, $bill->paymentRequests()->count());
        $this->assertSame('20000.00', $otherBill->fresh()->remaining_balance);

        $this->actingAs($admin)->get(route('admin.payment-requests.index', ['status' => 'Pending']))
            ->assertOk()
            ->assertSee($profile->student_number)
            ->assertSee('Confirm receipt');
        $this->actingAs($admin)->post(route('admin.payment-requests.decide', $paymentRequest), [
            'decision' => 'approve',
            'method' => 'Office Payment',
            'reference' => 'RECEIPT-001',
        ])->assertRedirect();

        $paymentRequest->refresh();
        $this->assertSame('Approved', $paymentRequest->status);
        $payment = Payment::where('reference', 'RECEIPT-001')->firstOrFail();
        $this->assertSame($admin->id, $payment->recorded_by);
        $this->assertSame('Office Payment', $payment->method);
        $this->assertSame('5000.00', $bill->fresh()->total_paid);
        $this->assertSame('15000.00', $bill->fresh()->remaining_balance);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $student->id,
            'type' => \App\Notifications\PaymentRequestStatusNotification::class,
        ]);
        $this->actingAs($admin)->post(route('admin.payment-requests.decide', $paymentRequest), [
            'decision' => 'approve',
            'method' => 'Office Payment',
        ])->assertSessionHasErrors('decision');
        $this->assertSame(1, $bill->payments()->count());
        $this->actingAs($student)->get(route('student.payments.index'))
            ->assertOk()
            ->assertSee('Payment request history')
            ->assertSee('Approved')
            ->assertSee('RECEIPT-001');

        $this->actingAs($admin)->get(route('admin.bills.show', $bill))
            ->assertOk()
            ->assertSee('RECEIPT-001');
    }

    public function test_student_dashboard_shows_four_tuition_installments_after_upfront_fees(): void
    {
        [, $student] = $this->billingSetup();
        FeeType::create(['name' => 'Enrollment Fee', 'amount' => '2000.00', 'is_active' => true]);
        FeeType::create(['name' => 'SF', 'amount' => '1500.00', 'is_active' => true]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Tuition installment plan')
            ->assertSee('₱21,000.00')
            ->assertSee('−₱2,000.00')
            ->assertSee('−₱1,500.00')
            ->assertSee('₱17,500.00')
            ->assertSee('Prelim')
            ->assertSee('Midterm')
            ->assertSee('Semi-final')
            ->assertSee('Final')
            ->assertSee('₱4,375.00');
    }

    public function test_payment_history_is_student_only_and_statements_remain_role_scoped(): void
    {
        [$admin, $student, $profile, $term] = $this->billingSetup();
        $otherProfile = $this->createStudentProfile();
        $bill = $this->createBill($profile, $term);
        $otherBill = $this->createBill($otherProfile, $term);
        $unpaidTerm = SchoolTerm::create(['school_year' => '2027-2028', 'semester' => '1st Semester']);
        $unpaidBill = $this->createBill($profile, $unpaidTerm);
        $payment = Payment::create([
            'bill_id' => $bill->id,
            'recorded_by' => $admin->id,
            'amount' => '1250.00',
            'paid_at' => now(),
            'method' => 'Cash',
            'reference' => 'PAY-HISTORY-001',
        ]);

        $this->actingAs($student)
            ->get(route('student.bills.statement', $bill))
            ->assertOk()
            ->assertSee('Statement of Account')
            ->assertSee($payment->reference)
            ->assertSee('Remaining balance');
        $this->actingAs($student)->get(route('student.bills.statement', $otherBill))->assertForbidden();
        $this->actingAs($student)
            ->get(route('student.payments.index'))
            ->assertOk()
            ->assertSee('PAY-HISTORY-001')
            ->assertSee($bill->invoice_number)
            ->assertSee($unpaidBill->invoice_number)
            ->assertSee('Partially Paid')
            ->assertSee('Unpaid')
            ->assertDontSee($otherBill->invoice_number);

        $this->actingAs($admin)
            ->get(route('admin.bills.statement', $bill))
            ->assertOk()
            ->assertSee('Statement of Account')
            ->assertSee('PAY-HISTORY-001');
        $this->actingAs($admin)
            ->get('/admin/payments')
            ->assertNotFound();
    }

    public function test_admin_can_correct_payments_from_bill_details_with_audit_history(): void
    {
        [$admin, , $profile, $term] = $this->billingSetup();
        $partialBill = $this->createBill($profile, $term);
        $payment = Payment::create([
            'bill_id' => $partialBill->id,
            'recorded_by' => $admin->id,
            'amount' => '300.00',
            'paid_at' => now()->subHour(),
            'method' => 'Cash',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.bills.show', $partialBill))
            ->assertOk()
            ->assertSee($partialBill->invoice_number)
            ->assertSee('₱300.00')
            ->assertSee('Edit payment')
            ->assertDontSee('Payment history');
        $this->actingAs($admin)->get('/admin/payments')->assertNotFound();

        $this->actingAs($admin)
            ->get(route('admin.payments.edit', $payment))
            ->assertOk()
            ->assertSee('Correction audit history');

        $this->actingAs($admin)
            ->from(route('admin.payments.edit', $payment))
            ->put(route('admin.payments.update', $payment), [
                'amount' => '20000.01',
                'paid_at' => now()->subMinutes(30)->format('Y-m-d\TH:i'),
                'method' => 'GCash',
            ])
            ->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payment_revisions', 0);

        $this->actingAs($admin)
            ->put(route('admin.payments.update', $payment), [
                'amount' => '450.00',
                'paid_at' => now()->subMinutes(30)->format('Y-m-d\TH:i'),
                'method' => 'GCash',
                'reference' => 'CORRECTED-REF',
                'notes' => 'Corrected after checking receipt.',
            ])
            ->assertRedirect(route('admin.bills.show', $partialBill));

        $this->assertSame('450.00', $payment->fresh()->amount);
        $this->assertDatabaseCount('payment_revisions', 1);
        $this->assertSame('300.00', $payment->revisions()->firstOrFail()->old_values['amount']);
        $this->assertSame('450.00', $payment->revisions()->firstOrFail()->new_values['amount']);
    }

    public function test_admin_and_student_billing_pages_render(): void
    {
        [$admin, $student, $profile, $term] = $this->billingSetup();
        $course = Course::create(['code' => 'BSIT', 'name' => 'Information Technology']);
        $profile->update(['course_id' => $course->id]);
        $bill = $this->createBill($profile, $term);

        $this->actingAs($admin)
            ->get(route('admin.catalog.index'))
            ->assertOk()
            ->assertSee('Fixed-amount scholarships')
            ->assertSee('Exam dates')
            ->assertSee('Payment due dates')
            ->assertSee(route('admin.exam-schedules.store'), false)
            ->assertSee(route('admin.billing-schedules.store'), false);
        $this->actingAs($admin)->get(route('admin.students.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.bills.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.bills.create'))
            ->assertOk()
            ->assertSee('Search by name, student ID, or course')
            ->assertSee('student.course')
            ->assertSee('Patot, Adams G.')
            ->assertSee('Information Technology')
            ->assertSee('studentNumber');
        $this->actingAs($admin)->get(route('admin.bills.index'))
            ->assertOk()
            ->assertSee('Search invoice, student, or course');
        $this->actingAs($admin)->get(route('admin.bills.show', $bill))->assertOk();
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Recent bills')
            ->assertSee($bill->invoice_number);
        $this->actingAs($student)
            ->get(route('student.bills.index'))
            ->assertOk()
            ->assertSee('My bills and balances');
        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Your account at a glance')
            ->assertSee($bill->invoice_number)
            ->assertSee('₱20,000.00');
    }

    public function test_course_and_school_term_can_be_updated_until_term_is_used(): void
    {
        [$admin, , $profile, $term] = $this->billingSetup();
        $course = Course::create(['code' => 'BSIT', 'name' => 'Information Technology']);

        $this->actingAs($admin)->put(route('admin.courses.update', $course), [
            'code' => 'BSCS',
            'name' => 'Computer Science',
        ])->assertRedirect();
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'code' => 'BSCS', 'name' => 'Computer Science']);

        $this->actingAs($admin)->put(route('admin.terms.update', $term), [
            'school_year' => '2027-2028',
            'semester' => '2nd Semester',
        ])->assertRedirect();
        $this->assertDatabaseHas('school_terms', [
            'id' => $term->id,
            'school_year' => '2027-2028',
            'semester' => '2nd Semester',
        ]);

        $bill = $this->createBill($profile, $term->fresh());
        $this->actingAs($admin)
            ->from(route('admin.catalog.index'))
            ->put(route('admin.terms.update', $term), [
                'school_year' => '2028-2029',
                'semester' => 'Summer',
            ])
            ->assertSessionHasErrors('school_term_id');
        $this->assertSame('2027-2028', $bill->fresh()->schoolTerm->school_year);
    }

    public function test_admin_can_delete_unused_catalog_records_but_not_records_referenced_by_billing(): void
    {
        [$admin, , $profile, $term, $fees, $scholarship] = $this->billingSetup();
        $unusedCourse = Course::create(['code' => 'UNUSED', 'name' => 'Unused course']);
        $assignedCourse = Course::create(['code' => 'USED', 'name' => 'Assigned course']);
        $profile->update(['course_id' => $assignedCourse->id]);

        $this->actingAs($admin)->delete(route('admin.courses.destroy', $unusedCourse))->assertRedirect();
        $this->assertDatabaseMissing('courses', ['id' => $unusedCourse->id]);
        $this->actingAs($admin)->delete(route('admin.courses.destroy', $assignedCourse))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('courses', ['id' => $assignedCourse->id]);

        $unusedTerm = SchoolTerm::create(['school_year' => '2028-2029', 'semester' => 'Summer']);
        $this->actingAs($admin)->delete(route('admin.terms.destroy', $unusedTerm))->assertRedirect();
        $this->assertDatabaseMissing('school_terms', ['id' => $unusedTerm->id]);
        $usedTerm = SchoolTerm::create(['school_year' => '2029-2030', 'semester' => 'Summer']);
        $this->createBill($profile, $usedTerm);
        $this->actingAs($admin)->delete(route('admin.terms.destroy', $usedTerm))->assertSessionHas('error');
        $this->assertDatabaseHas('school_terms', ['id' => $usedTerm->id]);

        $unusedFee = FeeType::create(['name' => 'Unused fee', 'amount' => '100.00', 'is_active' => true]);
        $this->actingAs($admin)->delete(route('admin.fees.destroy', $unusedFee))->assertRedirect();
        $this->assertDatabaseMissing('fee_types', ['id' => $unusedFee->id]);
        $usedFee = $fees->first();
        $bill = $this->createBill($profile, $term);
        $bill->items()->create([
            'fee_type_id' => $usedFee->id,
            'description' => $usedFee->name,
            'quantity' => 1,
            'unit_amount' => $usedFee->amount,
            'line_total' => $usedFee->amount,
        ]);
        $this->actingAs($admin)->delete(route('admin.fees.destroy', $usedFee))->assertSessionHas('error');
        $this->assertDatabaseHas('fee_types', ['id' => $usedFee->id]);

        $unusedScholarship = Scholarship::create(['name' => 'Unused award', 'discount_amount' => '100.00']);
        $this->actingAs($admin)->delete(route('admin.scholarships.destroy', $unusedScholarship))->assertRedirect();
        $this->assertDatabaseMissing('scholarships', ['id' => $unusedScholarship->id]);
        $this->actingAs($admin)->delete(route('admin.scholarships.destroy', $scholarship))->assertSessionHas('error');
        $this->assertDatabaseHas('scholarships', ['id' => $scholarship->id]);
    }

    public function test_admin_can_create_update_and_safely_delete_bills_without_financial_history(): void
    {
        [$admin, , $profile, $term, $fees] = $this->billingSetup();
        $bill = $this->createBill($profile, $term);
        $selectedFees = $fees->pluck('id')->all();

        $this->actingAs($admin)->get(route('admin.bills.edit', $bill))
            ->assertOk()
            ->assertSee('Edit student bill');
        $this->actingAs($admin)->put(route('admin.bills.update', $bill), [
            'student_profile_id' => $profile->id,
            'school_term_id' => $term->id,
            'fee_ids' => [$selectedFees[0]],
            'billing_date' => '2026-10-10',
            'notes' => 'Corrected before payment.',
        ])->assertRedirect(route('admin.bills.show', $bill));

        $this->assertSame('20000.00', $bill->fresh()->total_fees);
        $this->assertSame('5000.00', $bill->fresh()->scholarship_discount);
        $this->assertSame('15000.00', $bill->fresh()->net_amount_due);
        $this->assertSame('Corrected before payment.', $bill->fresh()->notes);
        $this->assertCount(1, $bill->fresh()->items);

        $this->actingAs($admin)->delete(route('admin.bills.destroy', $bill))
            ->assertRedirect(route('admin.bills.index'));
        $this->assertDatabaseMissing('bills', ['id' => $bill->id]);

        $protectedBill = $this->createBill($profile, $term);
        Payment::create([
            'bill_id' => $protectedBill->id,
            'recorded_by' => $admin->id,
            'amount' => '100.00',
            'paid_at' => now(),
            'method' => 'Cash',
        ]);
        $this->actingAs($admin)->get(route('admin.bills.edit', $protectedBill))
            ->assertRedirect(route('admin.bills.show', $protectedBill))
            ->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('admin.bills.destroy', $protectedBill))
            ->assertRedirect(route('admin.bills.show', $protectedBill))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('bills', ['id' => $protectedBill->id]);
    }

    public function test_admin_accounts_with_payment_audit_attribution_cannot_be_deleted(): void
    {
        [$admin, , $profile, $term] = $this->billingSetup();
        $recordingAdmin = User::factory()->create();
        $recordingAdmin->forceFill(['role' => User::ROLE_ADMIN])->save();
        $bill = $this->createBill($profile, $term);
        $payment = Payment::create([
            'bill_id' => $bill->id,
            'recorded_by' => $recordingAdmin->id,
            'amount' => '100.00',
            'paid_at' => now(),
            'method' => 'Cash',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $recordingAdmin))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $recordingAdmin->id]);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'recorded_by' => $recordingAdmin->id,
        ]);
    }

    public function test_admin_can_search_and_filter_bills_by_student_invoice_and_payment_status(): void
    {
        [$admin, , $profile, $term] = $this->billingSetup();
        $bill = $this->createBill($profile, $term);

        $this->actingAs($admin)->get(route('admin.bills.index', ['search' => 'STU-']))
            ->assertOk()
            ->assertSee($bill->invoice_number);
        $this->actingAs($admin)->get(route('admin.bills.index', ['status' => 'Unpaid']))
            ->assertOk()
            ->assertSee($bill->invoice_number);
        $this->actingAs($admin)->get(route('admin.bills.index', ['status' => 'Fully Paid']))
            ->assertOk()
            ->assertDontSee($bill->invoice_number);

        Payment::create([
            'bill_id' => $bill->id,
            'recorded_by' => $admin->id,
            'amount' => '5000.00',
            'paid_at' => now(),
            'method' => 'Cash',
        ]);
        $this->actingAs($admin)->get(route('admin.bills.index', ['status' => 'Partially Paid']))
            ->assertOk()
            ->assertSee($bill->invoice_number);
        $this->actingAs($admin)->get(route('admin.bills.index', ['search' => 'does-not-exist']))
            ->assertOk()
            ->assertSee('No bills match these filters.');
    }

    public function test_admin_can_create_and_edit_student_scholarship_assignment(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();
        $course = Course::create(['code' => 'BSIT', 'name' => 'Information Technology']);
        $scholarship = Scholarship::create(['name' => 'Student Assistance', 'discount_amount' => '3000.00']);

        $payload = [
            'first_name' => 'Riley',
            'middle_name' => '',
            'last_name' => 'Student',
            'course_id' => $course->id,
            'year_level' => '1st Year',
            'username' => 'RileyStudent',
            'email' => 'Riley@Example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $this->actingAs($admin)->post(route('admin.students.store'), $payload)
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'School ID: 16300001'));
        $student = StudentProfile::where('student_number', '16300001')->firstOrFail();
        $account = $student->user;
        $this->assertSame('rileystudent', $account->username);
        $this->assertSame('riley@example.test', $account->email);
        $this->assertNotNull($account->email_verified_at);
        $this->assertSame($course->id, $student->course_id);
        $this->assertNull($student->scholarship_id);

        $payload['username'] = 'rileyscholar';
        $payload['email'] = 'rileyscholar@example.test';
        $payload['is_scholar'] = '1';
        $payload['scholarship_id'] = $scholarship->id;
        $this->actingAs($admin)->post(route('admin.students.store'), $payload)->assertRedirect();
        $scholar = StudentProfile::where('student_number', '16300002')->firstOrFail();
        $this->assertSame($scholarship->id, $scholar->scholarship_id);

        $this->actingAs($admin)->get(route('admin.students.edit', $scholar))
            ->assertOk()
            ->assertSee('Is this student a scholar?')
            ->assertSee('BSIT — Information Technology');
        $this->actingAs($admin)->put(route('admin.students.update', $scholar), [
            'first_name' => 'Riley',
            'middle_name' => '',
            'last_name' => 'Student',
            'course_id' => $course->id,
            'year_level' => '2nd Year',
        ])->assertRedirect();
        $this->assertNull($scholar->fresh()->scholarship_id);

        $this->post('/logout');
        Auth::forgetGuards();
        $this->post('/login', ['username' => 'RileyStudent', 'password' => 'password123'])
            ->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($account);
        $this->assertSame(User::ROLE_STUDENT, $account->fresh()->role);
        $this->get(route('student.dashboard'))->assertOk();

        $this->post('/logout');
        Auth::forgetGuards();
        $this->post('/login', ['username' => 'RILEY@EXAMPLE.TEST', 'password' => 'password123'])
            ->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($account);
    }

    public function test_student_form_and_billing_setup_expose_course_and_scholarship_creation(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();

        $this->actingAs($admin)->get(route('admin.students.create'))
            ->assertOk()
            ->assertSee('Select a course')
            ->assertSee('Add a course in Billing setup')
            ->assertSee('Billing setup → Scholarships');

        $this->get(route('admin.catalog.index'))
            ->assertOk()
            ->assertSee('Add course')
            ->assertSee('Add scholarship')
            ->assertSee('id="courses"', false)
            ->assertSee('id="scholarships"', false);

        $this->post(route('admin.courses.store'), [
            'code' => 'BSA',
            'name' => 'Accountancy',
        ])->assertRedirect();
        $this->post(route('admin.scholarships.store'), [
            'name' => 'Academic Award',
            'discount_amount' => '5000.00',
        ])->assertRedirect();

        $this->assertDatabaseHas('courses', ['code' => 'BSA', 'name' => 'Accountancy']);
        $this->assertDatabaseHas('scholarships', ['name' => 'Academic Award', 'discount_amount' => '5000.00']);
        $this->get(route('admin.students.create'))
            ->assertOk()
            ->assertSee('BSA — Accountancy')
            ->assertSee('Academic Award — ₱5,000.00');
    }

    public function test_student_year_level_is_limited_to_first_through_fourth_year(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();
        $course = Course::create(['code' => 'BSIT', 'name' => 'Information Technology']);

        $this->actingAs($admin)
            ->get(route('admin.students.create'))
            ->assertOk()
            ->assertSee('4th Year')
            ->assertDontSee('5th Year')
            ->assertSee('Assigned automatically starting at 16300001');

        $existingStudent = $this->createStudentProfile();
        $existingStudent->update(['student_number' => '16300001']);

        $payload = [
            'first_name' => 'Year',
            'last_name' => 'Student',
            'course_id' => $course->id,
            'year_level' => '5th Year',
            'username' => 'yearstudent',
            'email' => 'yearstudent@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $this->actingAs($admin)
            ->from(route('admin.students.create'))
            ->post(route('admin.students.store'), $payload)
            ->assertSessionHasErrors('year_level');
        $this->assertDatabaseMissing('users', ['username' => 'yearstudent']);

        $payload['year_level'] = '4th Year';
        $this->actingAs($admin)->post(route('admin.students.store'), $payload)->assertRedirect();
        $student = StudentProfile::where('student_number', '16300002')->firstOrFail();
        $this->assertSame('4th Year', $student->year_level);

        $this->actingAs($admin)
            ->from(route('admin.students.edit', $student))
            ->put(route('admin.students.update', $student), [
                'first_name' => 'Year',
                'last_name' => 'Student',
                'student_number' => '16300099',
                'course_id' => $course->id,
                'year_level' => '5th Year',
            ])
            ->assertSessionHasErrors('year_level');
        $this->assertSame('4th Year', $student->fresh()->year_level);
        $this->actingAs($admin)->put(route('admin.students.update', $student), [
            'first_name' => 'Year',
            'last_name' => 'Student',
            'course_id' => $course->id,
            'year_level' => '4th Year',
            'student_number' => '16300099',
        ])->assertRedirect();
        $this->assertSame('16300002', $student->fresh()->student_number);
    }

    public function test_admin_must_confirm_student_id_before_deleting_student_account(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();
        $student = $this->createStudentProfile();
        $userId = $student->user_id;

        $this->actingAs($admin)
            ->get(route('admin.students.index'))
            ->assertOk()
            ->assertSee('Final approval required')
            ->assertSee('Type the student ID')
            ->assertSee($student->student_number);

        $this->actingAs($admin)
            ->delete(route('admin.students.destroy', $student), ['confirm_student_number' => 'WRONG-ID'])
            ->assertRedirect(route('admin.students.index'))
            ->assertSessionHasErrors('confirm_student_number');

        $this->assertDatabaseHas('student_profiles', ['id' => $student->id]);
        $this->assertDatabaseHas('users', ['id' => $userId]);

        $this->actingAs($admin)
            ->delete(route('admin.students.destroy', $student), ['confirm_student_number' => $student->student_number])
            ->assertRedirect(route('admin.students.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('student_profiles', ['id' => $student->id]);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_admin_can_confirm_delete_student_with_all_billing_and_promissory_history(): void
    {
        [$admin, , $student, $term] = $this->billingSetup();
        $nextTerm = SchoolTerm::create(['school_year' => '2027-2028', 'semester' => '1st Semester']);
        $bill = $this->createBill($student, $term);
        $payment = Payment::create([
            'bill_id' => $bill->id,
            'recorded_by' => $admin->id,
            'amount' => '2500.00',
            'paid_at' => now(),
            'method' => 'Cash',
        ]);
        $revision = $payment->revisions()->create([
            'edited_by' => $admin->id,
            'old_values' => ['amount' => '2000.00'],
            'new_values' => ['amount' => '2500.00'],
        ]);
        $letter = $this->createLetter($student, $bill, 'Approved');
        BillExtension::create([
            'promissory_letter_id' => $letter->id,
            'source_bill_id' => $bill->id,
            'next_school_term_id' => $nextTerm->id,
            'amount_at_approval' => $bill->remaining_balance,
            'approved_until' => today()->addDays(30),
        ]);
        $notification = $student->user->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'test-notification',
            'data' => ['message' => 'Test'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.students.index'))
            ->assertOk()
            ->assertSee('All related billing and payment records will also be deleted')
            ->assertSee(route('admin.students.destroy', $student), false);

        $this->actingAs($admin)
            ->delete(route('admin.students.destroy', $student), ['confirm_student_number' => $student->student_number])
            ->assertRedirect(route('admin.students.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('student_profiles', ['id' => $student->id]);
        $this->assertDatabaseMissing('users', ['id' => $student->user_id]);
        $this->assertDatabaseMissing('bills', ['id' => $bill->id]);
        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
        $this->assertDatabaseMissing('payment_revisions', ['id' => $revision->id]);
        $this->assertDatabaseMissing('promissory_letters', ['id' => $letter->id]);
        $this->assertDatabaseMissing('bill_extensions', ['promissory_letter_id' => $letter->id]);
        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_student_can_preview_submit_and_print_own_promissory_request_only(): void
    {
        [, $student, $profile, $term] = $this->billingSetup();
        $otherProfile = $this->createStudentProfile();
        $bill = $this->createBill($profile, $term);
        $otherBill = $this->createBill($otherProfile, $term);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Request promissory letter')
            ->assertSee(route('student.promissory-letters.create', $bill), false);
        $this->actingAs($student)->get(route('student.promissory-letters.create', $bill))
            ->assertOk()
            ->assertSee('LIVE PREVIEW')
            ->assertSee('Request for Extension of Payment')
            ->assertSee('The School Administrator')
            ->assertSee('Student signature')
            ->assertSee('₱20,000.00')
            ->assertSee('Deferral requested')
            ->assertSee($bill->invoice_number);
        $this->actingAs($student)->get(route('student.promissory-letters.create', $otherBill))->assertForbidden();
        $this->actingAs($student)->post(route('student.promissory-letters.store'), [
            'bill_id' => $otherBill->id,
            'reason' => 'Trying to submit for another student.',
            'commitment_date' => today()->addDays(30)->toDateString(),
        ])->assertForbidden();

        $this->actingAs($student)->post(route('student.promissory-letters.store'), [
            'bill_id' => $bill->id,
            'reason' => 'My family has an unexpected medical expense.',
            'commitment_date' => today()->addDays(30)->toDateString(),
        ])->assertRedirect();

        $letter = PromissoryLetter::firstOrFail();
        $this->assertSame('Pending', $letter->status);
        $this->assertSame('20,000.00', number_format((float) $letter->outstanding_amount, 2, '.', ','));
        $this->assertSame('20000.00', $letter->requested_amount);
        $this->actingAs($student)->get(route('student.promissory-letters.show', $letter))
            ->assertOk()
            ->assertSee('PROMISSORY LETTER')
            ->assertSee('My family has an unexpected medical expense.');
        $this->actingAs($student)->get(route('student.promissory-letters.show', PromissoryLetter::create([
            'student_profile_id' => $otherProfile->id,
            'bill_id' => $otherBill->id,
            'student_name' => 'Other Student',
            'student_number' => $otherProfile->student_number,
            'school_year' => $term->school_year,
            'semester' => $term->semester,
            'invoice_number' => $otherBill->invoice_number,
            'outstanding_amount' => '20000.00',
            'reason' => 'Test request',
            'commitment_date' => today()->addDays(30),
            'submitted_at' => now(),
            'status' => 'Pending',
        ])))->assertForbidden();
    }

    public function test_approving_and_rejecting_promissory_requests_preserves_original_bill_and_carries_balance_once(): void
    {
        [$admin, $student, $profile, $term] = $this->billingSetup();
        $this->createBillingSchedule($term, 'Semi-final');
        $bill = $this->createBill($profile, $term, 'Midterm');
        Payment::create([
            'bill_id' => $bill->id,
            'recorded_by' => $admin->id,
            'amount' => '5000.00',
            'paid_at' => now(),
            'method' => 'Cash',
        ]);
        $letter = $this->createLetter($profile, $bill, 'Pending');
        $otherProfile = $this->createStudentProfile();
        $otherBill = $this->createBill($otherProfile, $term);
        $anotherLetter = $this->createLetter($otherProfile, $otherBill, 'Pending');

        $this->actingAs($admin)->post(route('admin.promissory-letters.decide', $letter), [
            'decision' => 'approve',
            'admin_note' => 'Approved for the next term.',
        ])->assertRedirect();

        $this->assertSame('Approved', $letter->fresh()->status);
        $this->assertSame('15000.00', $letter->extension->amount_at_approval);
        $this->assertSame('15000.00', $letter->extension->remaining_balance);
        $this->assertSame('Semi-final', $letter->extension->next_billing_period);
        $this->assertSame($letter->invoice_number, $bill->fresh()->invoice_number);
        $this->assertSame('5000.00', $bill->fresh()->total_paid);
        $this->assertSame('0.00', $bill->fresh()->remaining_balance);
        $this->assertSame('15000.00', $letter->extension->destinationBill->remaining_balance);
        $this->assertSame(today()->addDays(30)->toDateString(), $letter->extension->destinationBill->due_date->toDateString());
        $this->assertSame(0, $letter->extension->destinationBill->payments()->count());
        $this->assertDatabaseCount('bill_extensions', 1);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $student->id]);

        $this->actingAs($admin)->post(route('admin.promissory-letters.decide', $letter), [
            'decision' => 'approve',
        ])->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('bill_extensions', 1);

        $this->actingAs($admin)->post(route('admin.promissory-letters.decide', $anotherLetter), [
            'decision' => 'reject',
            'admin_note' => 'Please provide a revised payment date.',
        ])->assertRedirect();
        $this->assertSame('Rejected', $anotherLetter->fresh()->status);
        $this->actingAs($otherProfile->user)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee(route('student.promissory-letters.create', $otherBill), false);
        $this->assertDatabaseCount('bill_extensions', 1);

        $this->actingAs($admin)->post(route('admin.bills.payments.store', $letter->extension->destinationBill), [
            'amount' => '2000.00',
            'paid_at' => now()->format('Y-m-d\TH:i'),
            'method' => 'Cash',
        ])->assertRedirect();
        $this->assertSame('13000.00', $letter->extension->fresh()->remaining_balance);
        $this->assertSame('0.00', $letter->extension->sourceBill->remaining_balance);

        $this->actingAs($student)->get(route('student.promissory-letters.index'))
            ->assertOk()
            ->assertSee('Balances carried to the next payment period')
            ->assertSee('₱13,000.00')
            ->assertSee('counted only once');
    }

    public function test_promissory_letter_can_request_and_admin_can_approve_a_partial_deferral(): void
    {
        [$admin, $student, $profile, $term] = $this->billingSetup();
        $this->createBillingSchedule($term, 'Semi-final');
        $bill = $this->createBill($profile, $term, 'Midterm');

        $this->actingAs($student)->post(route('student.promissory-letters.store'), [
            'bill_id' => $bill->id,
            'reason' => 'My family has an unexpected medical expense.',
            'requested_amount' => '8000.00',
            'commitment_date' => today()->addDays(30)->toDateString(),
        ])->assertRedirect();

        $letter = PromissoryLetter::firstOrFail();
        $this->assertSame('8000.00', $letter->requested_amount);
        $this->actingAs($admin)->get(route('admin.promissory-letters.show', $letter))
            ->assertOk()
            ->assertSee('Amount to defer')
            ->assertSee('value="8000.00"', false);

        $this->actingAs($admin)->post(route('admin.promissory-letters.decide', $letter), [
            'decision' => 'approve',
            'deferred_amount' => '8000.01',
        ])->assertSessionHasErrors('deferred_amount');
        $this->assertSame('Pending', $letter->fresh()->status);

        $this->actingAs($admin)->post(route('admin.promissory-letters.decide', $letter), [
            'decision' => 'approve',
            'deferred_amount' => '5000.00',
        ])->assertRedirect();

        $this->assertSame('Approved', $letter->fresh()->status);
        $this->assertSame('5000.00', $letter->extension->amount_at_approval);
        $this->assertSame('5000.00', $letter->extension->remaining_balance);
        $this->assertSame('15000.00', $bill->fresh()->remaining_balance);
        $this->assertSame('5000.00', $letter->extension->destinationBill->remaining_balance);
    }

    public function test_semi_final_promissory_approval_moves_only_the_requested_amount_to_final(): void
    {
        [$admin, , $profile, $term] = $this->billingSetup();
        $this->createBillingSchedule($term, 'Final');
        $bill = $this->createBill($profile, $term, 'Semi-final');
        $bill->update([
            'total_fees' => '5000.00',
            'scholarship_discount' => '0.00',
            'net_amount_due' => '5000.00',
        ]);
        $letter = $this->createLetter($profile, $bill, 'Pending');
        $letter->update(['requested_amount' => '3000.00']);

        $this->actingAs($admin)->post(route('admin.promissory-letters.decide', $letter), [
            'decision' => 'approve',
            'deferred_amount' => '3000.00',
            'admin_note' => 'Approved for the final period.',
        ])->assertRedirect();

        $extension = $letter->fresh()->extension;
        $this->assertSame('Final', $extension->next_billing_period);
        $this->assertSame('Final', $extension->destinationBill->billing_period);
        $this->assertSame('2000.00', $bill->fresh()->remaining_balance);
        $this->assertSame('3000.00', $extension->destinationBill->remaining_balance);
        $this->assertSame('5000.00', number_format(
            (float) $bill->fresh()->period_balance + (float) $extension->destinationBill->period_balance,
            2,
            '.',
            ''
        ));
    }

    public function test_student_can_request_payment_on_the_destination_period_and_admin_confirmation_reduces_carryforward(): void
    {
        [$admin, $student, $profile, $term] = $this->billingSetup();
        $this->createBillingSchedule($term, 'Semi-final');
        $bill = $this->createBill($profile, $term, 'Midterm');
        $bill->update([
            'total_fees' => '5000.00',
            'scholarship_discount' => '0.00',
            'net_amount_due' => '5000.00',
        ]);
        $letter = $this->createLetter($profile, $bill, 'Pending');

        $this->actingAs($admin)->post(route('admin.promissory-letters.decide', $letter), [
            'decision' => 'approve',
            'deferred_amount' => '5000.00',
        ])->assertRedirect();

        $destinationBill = $letter->fresh()->extension->destinationBill;
        $this->actingAs($student)->post(route('student.bills.payments.store', $destinationBill), [
            'amount' => '2000.00',
            'reference' => 'SEMIFINAL-REF-001',
        ])->assertRedirect();

        $paymentRequest = PaymentRequest::firstOrFail();
        $this->assertSame('5000.00', $destinationBill->fresh()->remaining_balance);

        $this->actingAs($admin)->post(route('admin.payment-requests.decide', $paymentRequest), [
            'decision' => 'approve',
            'method' => 'Bank transfer',
        ])->assertRedirect();

        $this->assertSame('Approved', $paymentRequest->fresh()->status);
        $this->assertSame('3000.00', $destinationBill->fresh()->remaining_balance);
        $this->assertSame('3000.00', $letter->fresh()->extension->remaining_balance);
        $this->assertSame('0.00', $bill->fresh()->remaining_balance);
        $this->assertDatabaseHas('payments', [
            'bill_id' => $destinationBill->id,
            'amount' => '2000.00',
            'reference' => 'SEMIFINAL-REF-001',
        ]);
        $this->actingAs($student)->get(route('student.payments.index'))
            ->assertOk()
            ->assertSee('SEMIFINAL-REF-001')
            ->assertSee('₱3,000.00');
    }

    public function test_new_period_fees_combine_with_the_approved_carryforward(): void
    {
        [$admin, , $profile, $term, $fees] = $this->billingSetup();
        $this->createBillingSchedule($term, 'Semi-final');
        $bill = $this->createBill($profile, $term, 'Midterm');
        $bill->update([
            'total_fees' => '5000.00',
            'scholarship_discount' => '0.00',
            'net_amount_due' => '5000.00',
        ]);
        $letter = $this->createLetter($profile, $bill, 'Pending');

        $this->actingAs($admin)->post(route('admin.promissory-letters.decide', $letter), [
            'decision' => 'approve',
            'deferred_amount' => '5000.00',
        ])->assertRedirect();

        $placeholder = $letter->fresh()->extension->destinationBill;
        $this->actingAs($admin)->post(route('admin.bills.store'), [
            'student_profile_id' => $profile->id,
            'school_term_id' => $term->id,
            'billing_period' => 'Semi-final',
            'fee_ids' => [$fees->firstWhere('name', 'Tuition')->id],
            'billing_date' => today()->toDateString(),
        ])->assertRedirect();

        $destinationBill = $placeholder->fresh();
        $this->assertSame($placeholder->id, $destinationBill->id);
        $this->assertSame('20000.00', $destinationBill->total_due);
        $this->assertSame('20000.00', $destinationBill->remaining_balance);
        $this->assertSame('5000.00', $destinationBill->incomingExtensionBalance);
        $this->assertDatabaseCount('bills', 2);
    }

    public function test_promissory_approval_does_not_require_a_next_period_due_date(): void
    {
        [$admin, , $profile, $term] = $this->billingSetup();
        $bill = $this->createBill($profile, $term, 'Midterm');
        $letter = $this->createLetter($profile, $bill, 'Pending');

        $this->actingAs($admin)->get(route('admin.promissory-letters.show', $letter))
            ->assertOk()
            ->assertSee('Next payment period')
            ->assertSee('No payment due date is configured yet. Approval is still allowed');

        $this->actingAs($admin)->post(route('admin.promissory-letters.decide', $letter), [
            'decision' => 'approve',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $extension = $letter->fresh()->extension;
        $this->assertSame('Approved', $letter->fresh()->status);
        $this->assertSame('Semi-final', $extension->next_billing_period);
        $this->assertNull($extension->destinationBill->due_date);
        $this->assertSame(0, $extension->destinationBill->payments()->count());
        $this->assertDatabaseCount('bill_extensions', 1);
    }

    public function test_fully_paid_bill_cannot_be_carried_forward_and_overdue_commitments_notify_admins(): void
    {
        [$admin, , $profile, $term] = $this->billingSetup();
        $nextTerm = SchoolTerm::create(['school_year' => '2026-2027', 'semester' => '2nd Semester']);
        $bill = $this->createBill($profile, $term);
        Payment::create([
            'bill_id' => $bill->id,
            'recorded_by' => $admin->id,
            'amount' => '20000.00',
            'paid_at' => now(),
            'method' => 'Cash',
        ]);
        $paidLetter = $this->createLetter($profile, $bill, 'Pending');
        $this->actingAs($admin)->post(route('admin.promissory-letters.decide', $paidLetter), [
            'decision' => 'approve',
            'next_school_term_id' => $nextTerm->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Fulfilled', $paidLetter->fresh()->status);
        $this->assertDatabaseCount('bill_extensions', 0);

        [$admin, , $profile, $term] = $this->billingSetup();
        $bill = $this->createBill($profile, $term);
        $letter = $this->createLetter($profile, $bill, 'Approved');
        $letter->update(['commitment_date' => today()->subDay()]);

        $this->artisan('promissory:mark-overdue')->assertSuccessful();
        $this->assertSame('Overdue', $letter->fresh()->status);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $admin->id]);

        Payment::create([
            'bill_id' => $bill->id,
            'recorded_by' => $admin->id,
            'amount' => '20000.00',
            'paid_at' => now(),
            'method' => 'Cash',
        ]);
        $this->artisan('promissory:mark-overdue')->assertSuccessful();
        $this->assertSame('Fulfilled', $letter->fresh()->status);
    }

    public function test_payment_cannot_exceed_remaining_balance_and_latest_is_by_date_then_id(): void
    {
        [$admin, , $profile, $term] = $this->billingSetup();
        $bill = $this->createBill($profile, $term);
        Payment::create([
            'bill_id' => $bill->id,
            'recorded_by' => $admin->id,
            'amount' => '100.00',
            'paid_at' => '2026-10-10 10:00:00',
            'method' => 'Cash',
        ]);
        $latest = Payment::create([
            'bill_id' => $bill->id,
            'recorded_by' => $admin->id,
            'amount' => '200.00',
            'paid_at' => '2026-10-10 10:00:00',
            'method' => 'Cash',
        ]);

        $this->assertSame($latest->id, $bill->latestPayment->id);
        $this->assertSame('300.00', $bill->total_paid);
        $this->assertSame('19700.00', $bill->remaining_balance);

        $this->actingAs($admin)
            ->from(route('admin.bills.show', $bill))
            ->post(route('admin.bills.payments.store', $bill), [
                'amount' => '0.001',
                'paid_at' => '2026-10-11T12:00',
                'method' => 'Cash',
            ])
            ->assertSessionHasErrors('amount');

        $this->actingAs($admin)
            ->from(route('admin.bills.show', $bill))
            ->post(route('admin.bills.payments.store', $bill), [
                'amount' => '19700.01',
                'paid_at' => '2026-10-11T12:00',
                'method' => 'Cash',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payments', 2);
    }

    private function billingSetup(string $discount = '5000.00'): array
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();
        $student = User::factory()->create();
        $profile = $student->studentProfile()->create([
            'student_number' => 'STU-'.fake()->unique()->numerify('####'),
            'first_name' => 'Adams',
            'middle_name' => 'Grace',
            'last_name' => 'Patot',
        ]);
        $term = SchoolTerm::firstOrCreate(['school_year' => '2026-2027', 'semester' => '1st Semester']);
        $fees = collect([
            FeeType::firstOrCreate(['name' => 'Tuition'], ['amount' => '20000.00']),
            FeeType::firstOrCreate(['name' => 'Miscellaneous'], ['amount' => '5000.00']),
        ]);
        $scholarship = Scholarship::firstOrCreate(['name' => 'Academic Scholarship'], ['discount_amount' => $discount]);
        $scholarship->update(['discount_amount' => $discount]);
        $profile->update(['scholarship_id' => $scholarship->id]);

        return [$admin, $student, $profile, $term, $fees, $scholarship];
    }

    private function createStudentProfile(): StudentProfile
    {
        $user = User::factory()->create();

        return $user->studentProfile()->create([
            'student_number' => 'STU-'.fake()->unique()->numerify('####'),
            'first_name' => 'Other',
            'last_name' => 'Student',
        ]);
    }

    private function createBill(StudentProfile $profile, SchoolTerm $term, string $period = 'Full Term'): Bill
    {
        return Bill::create([
            'invoice_number' => 'INV-TEST-'.$profile->id.'-'.$term->id.'-'.$period,
            'student_profile_id' => $profile->id,
            'school_term_id' => $term->id,
            'billing_period' => $period,
            'total_fees' => '25000.00',
            'scholarship_discount' => '5000.00',
            'net_amount_due' => '20000.00',
            'billing_date' => '2026-10-01',
        ]);
    }

    private function createBillingSchedule(SchoolTerm $term, string $period): BillingSchedule
    {
        return BillingSchedule::create([
            'school_term_id' => $term->id,
            'billing_period' => $period,
            'due_date' => today()->addDays(30),
            'scope_key' => 'global-'.$period,
        ]);
    }

    private function createLetter(StudentProfile $profile, Bill $bill, string $status): PromissoryLetter
    {
        return PromissoryLetter::create([
            'student_profile_id' => $profile->id,
            'bill_id' => $bill->id,
            'student_name' => $profile->invoice_name,
            'student_number' => $profile->student_number,
            'school_year' => $bill->schoolTerm->school_year,
            'semester' => $bill->schoolTerm->semester,
            'invoice_number' => $bill->invoice_number,
            'outstanding_amount' => $bill->remaining_balance,
            'reason' => 'A temporary hardship has affected my ability to pay on time.',
            'commitment_date' => today()->addDays(20),
            'submitted_at' => now(),
            'status' => $status,
        ]);
    }
}
