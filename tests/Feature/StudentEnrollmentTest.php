<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\SchoolTerm;
use App\Models\Subject;
use App\Models\SubjectWithdrawalRefund;
use App\Models\SubjectWithdrawalRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_their_student_details_and_subjects(): void
    {
        [$student, $profile, $enrollment] = $this->enrollmentSetup();
        [, $otherProfile] = $this->enrollmentSetup();
        $otherProfile->update(['last_name' => 'Another']);
        $otherProfile->refresh();

        $this->actingAs($student)
            ->get(route('student.enrollment.index'))
            ->assertOk()
            ->assertSee($profile->invoice_name)
            ->assertSee($profile->course->code)
            ->assertSee('2nd Year')
            ->assertSee('View subjects')
            ->assertSee($enrollment->subject->code)
            ->assertDontSee($otherProfile->invoice_name);
    }

    public function test_student_withdrawal_requires_admin_approval_and_adjusts_bill_and_refund(): void
    {
        [$student, $profile, $enrollment, $term] = $this->enrollmentSetup();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();
        $bill = Bill::create([
            'invoice_number' => 'INV-WITHDRAWAL-TEST',
            'student_profile_id' => $profile->id,
            'school_term_id' => $term->id,
            'billing_period' => 'Full Term',
            'total_fees' => '27500.00',
            'scholarship_discount' => '5000.00',
            'net_amount_due' => '22500.00',
            'billing_date' => today(),
        ]);
        $enrollment->update(['bill_id' => $bill->id]);
        $subjectItem = BillItem::create([
            'bill_id' => $bill->id,
            'description' => 'Subject '.$enrollment->subject->code.' — '.$enrollment->subject->name.' (3.0 units)',
            'quantity' => 1,
            'unit_amount' => '2500.00',
            'line_total' => '2500.00',
        ]);
        Payment::create([
            'bill_id' => $bill->id,
            'recorded_by' => $admin->id,
            'amount' => '22000.00',
            'balance_after' => '500.00',
            'paid_at' => now(),
            'method' => 'Cash',
        ]);

        $this->actingAs($student)
            ->post(route('student.enrollment.withdrawals.store', $enrollment))
            ->assertRedirect()
            ->assertSessionHas('status');
        $withdrawalRequest = SubjectWithdrawalRequest::firstOrFail();
        $this->assertSame('Pending', $withdrawalRequest->status);
        $this->assertNull($enrollment->fresh()->withdrawn_at);
        $this->assertSame('22500.00', $bill->fresh()->net_amount_due);

        $this->actingAs($admin)
            ->get(route('admin.subject-withdrawals.index'))
            ->assertOk()
            ->assertSee($profile->invoice_name)
            ->assertSee('Approve withdrawal');
        $this->actingAs($admin)
            ->post(route('admin.subject-withdrawals.decide', $withdrawalRequest), ['decision' => 'approve'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $withdrawalRequest->refresh();
        $this->assertSame('Approved', $withdrawalRequest->status);
        $this->assertSame('2500.00', $withdrawalRequest->credited_amount);
        $this->assertNotNull($enrollment->fresh()->withdrawn_at);
        $this->assertSame('25000.00', $bill->fresh()->total_fees);
        $this->assertSame('20000.00', $bill->fresh()->net_amount_due);
        $this->assertSame('0.00', $subjectItem->fresh()->line_total);

        $refund = SubjectWithdrawalRefund::firstOrFail();
        $this->assertSame('2000.00', $refund->amount);
        $this->assertSame('Pending', $refund->status);
        $this->actingAs($admin)
            ->post(route('admin.subject-withdrawals.refunds.paid', $refund), [
                'method' => 'Cash',
                'reference' => 'REFUND-001',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Cash refund recorded as paid.');
        $this->assertSame('Paid', $refund->fresh()->status);

        $this->actingAs($student)
            ->get(route('student.enrollment.index'))
            ->assertOk()
            ->assertSee('Withdrawn')
            ->assertSee('Refund: ₱2,000.00 · Paid')
            ->assertDontSee('Request withdrawal');
    }

    public function test_admin_must_give_a_reason_to_reject_a_withdrawal_request(): void
    {
        [$student, $profile, $enrollment] = $this->enrollmentSetup();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();

        $this->actingAs($student)->post(route('student.enrollment.withdrawals.store', $enrollment));
        $withdrawalRequest = SubjectWithdrawalRequest::firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.subject-withdrawals.index'))
            ->post(route('admin.subject-withdrawals.decide', $withdrawalRequest), [
                'decision' => 'reject',
                'admin_note' => '',
            ])
            ->assertSessionHasErrors('admin_note');

        $this->assertSame('Pending', $withdrawalRequest->fresh()->status);
        $this->assertNull($enrollment->fresh()->withdrawn_at);
        $this->assertSame($profile->id, $withdrawalRequest->student_profile_id);
    }

    public function test_student_cannot_request_withdrawal_from_another_students_subject(): void
    {
        [$student] = $this->enrollmentSetup();
        [, , $otherEnrollment] = $this->enrollmentSetup();

        $this->actingAs($student)
            ->post(route('student.enrollment.withdrawals.store', $otherEnrollment))
            ->assertNotFound();

        $this->assertDatabaseCount('subject_withdrawal_requests', 0);
    }

    public function test_admin_student_deletion_removes_withdrawal_and_refund_records(): void
    {
        [$student, $profile, $enrollment] = $this->enrollmentSetup();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();
        $withdrawalRequest = SubjectWithdrawalRequest::create([
            'enrollment_id' => $enrollment->id,
            'student_profile_id' => $profile->id,
            'status' => 'Approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
        SubjectWithdrawalRefund::create([
            'subject_withdrawal_request_id' => $withdrawalRequest->id,
            'amount' => '100.00',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.students.destroy', $profile), [
                'confirm_student_number' => $profile->student_number,
            ])
            ->assertRedirect(route('admin.students.index'));

        $this->assertDatabaseMissing('users', ['id' => $student->id]);
        $this->assertDatabaseCount('subject_withdrawal_requests', 0);
        $this->assertDatabaseCount('subject_withdrawal_refunds', 0);
    }

    private function enrollmentSetup(): array
    {
        $course = Course::create(['code' => 'BSCS-'.fake()->unique()->numerify('###'), 'name' => 'Computer Science']);
        $student = User::factory()->create();
        $profile = $student->studentProfile()->create([
            'student_number' => 'STU-'.fake()->unique()->numerify('####'),
            'first_name' => 'Taylor',
            'last_name' => 'Student',
            'course_id' => $course->id,
            'year_level' => '2nd Year',
        ]);
        $term = SchoolTerm::firstOrCreate(['school_year' => '2026-2027', 'semester' => '1st Semester']);
        $subject = Subject::create([
            'course_id' => $course->id,
            'code' => 'CS201-'.fake()->unique()->numerify('###'),
            'name' => 'Data Structures',
            'units' => '3.0',
            'year_level' => '2nd Year',
            'semester' => $term->semester,
        ]);
        $enrollment = Enrollment::create([
            'student_profile_id' => $profile->id,
            'subject_id' => $subject->id,
            'school_term_id' => $term->id,
            'units' => $subject->units,
        ]);

        return [$student, $profile, $enrollment, $term];
    }
}
