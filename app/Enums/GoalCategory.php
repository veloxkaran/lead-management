<?php

namespace App\Enums;

enum GoalCategory: string
{
    case MonthlyRevenueTarget = 'monthly_revenue_target';
    case QuarterlyRevenueTarget = 'quarterly_revenue_target';
    case AnnualRevenueTarget = 'annual_revenue_target';
    case NewClientAcquisition = 'new_client_acquisition';
    case SubscriptionRenewals = 'subscription_renewals';
    case CustomerRetention = 'customer_retention';
    case ImplementationCompletion = 'implementation_completion';
    case TrainingCompletion = 'training_completion';
    case CollectionsTarget = 'collections_target';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MonthlyRevenueTarget => 'Monthly Revenue Target',
            self::QuarterlyRevenueTarget => 'Quarterly Revenue Target',
            self::AnnualRevenueTarget => 'Annual Revenue Target',
            self::NewClientAcquisition => 'New Client Acquisition',
            self::SubscriptionRenewals => 'Subscription Renewals',
            self::CustomerRetention => 'Customer Retention',
            self::ImplementationCompletion => 'Implementation Completion',
            self::TrainingCompletion => 'Training Completion',
            self::CollectionsTarget => 'Collections Target',
            self::Other => 'Other',
        };
    }

    /**
     * Whether a Deal Closure can automatically feed this category's
     * achievement today — see GoalContributionService. Categories not
     * listed here (Subscription Renewals, Customer Retention,
     * Implementation/Training Completion, Collections Target, Other) stay
     * manual/0 until their own business event is wired up.
     */
    public function isDealDriven(): bool
    {
        return in_array($this, [
            self::MonthlyRevenueTarget,
            self::QuarterlyRevenueTarget,
            self::AnnualRevenueTarget,
            self::NewClientAcquisition,
        ], true);
    }

    /**
     * New Client Acquisition counts deals closed, not their summed value —
     * every other deal-driven category sums the raw deal value instead.
     */
    public function aggregatesByCount(): bool
    {
        return $this === self::NewClientAcquisition;
    }
}
