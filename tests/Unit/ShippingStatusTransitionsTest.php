<?php

namespace Tests\Unit;

use App\Enums\StatusShippingRequestEnum as Status;
use PHPUnit\Framework\TestCase;

class ShippingStatusTransitionsTest extends TestCase
{
    public function test_happy_path_is_allowed_in_order(): void
    {
        $path = [
            Status::AwaitingCustomerApproval,
            Status::AwaitingCustomerData,
            Status::ReadyForReview,
            Status::SearchingCarrier,
            Status::AwaitingPayment,
            Status::Paid,
            Status::BookingConfirmed,
            Status::PickedUp,
            Status::InTransit,
            Status::Delivered,
        ];

        for ($i = 0; $i < count($path) - 1; $i++) {
            $this->assertTrue($path[$i]->canTransitionTo($path[$i + 1]), "{$path[$i]->value} -> {$path[$i + 1]->value}");
        }
    }

    public function test_payment_and_booking_cannot_be_skipped(): void
    {
        $this->assertFalse(Status::SearchingCarrier->canTransitionTo(Status::Paid));
        $this->assertFalse(Status::ReadyForReview->canTransitionTo(Status::AwaitingPayment));
        $this->assertFalse(Status::AwaitingPayment->canTransitionTo(Status::BookingConfirmed));
        $this->assertFalse(Status::AwaitingCustomerApproval->canTransitionTo(Status::BookingConfirmed));
    }

    public function test_terminal_and_legacy_statuses_go_nowhere(): void
    {
        foreach ([Status::Delivered, Status::Unavailable, Status::Canceled, Status::Pending, Status::InProgress, Status::Completed] as $status) {
            $this->assertTrue($status->isTerminal(), $status->value);
            $this->assertSame([], $status->allowedNext(), $status->value);
        }
    }

    public function test_every_open_status_can_still_be_closed(): void
    {
        $open = [
            Status::AwaitingCustomerApproval, Status::AwaitingCustomerData, Status::ReadyForReview,
            Status::SearchingCarrier, Status::AwaitingPayment, Status::Paid, Status::BookingConfirmed,
        ];

        foreach ($open as $status) {
            $this->assertTrue($status->canTransitionTo(Status::Canceled), $status->value);
        }
    }

    public function test_only_legacy_statuses_are_flagged_legacy(): void
    {
        $this->assertTrue(Status::Pending->isLegacy());
        $this->assertFalse(Status::AwaitingPayment->isLegacy());
    }

    public function test_every_status_has_an_arabic_label(): void
    {
        foreach (Status::cases() as $status) {
            $this->assertNotSame($status->value, Status::trans($status->value), $status->value);
        }
    }
}
