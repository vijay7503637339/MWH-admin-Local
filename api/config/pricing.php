<?php
declare(strict_types=1);

/*
 * MWH Local staff payout policy.
 * Contractors are charged the job-role master amount.
 * Staff receive the role amount after the company commission.
 */
const LOCAL_STAFF_COMMISSION_PERCENT = 20.0;

function localStaffNetAmount(float $grossAmount): float
{
    $net = $grossAmount * (1 - (LOCAL_STAFF_COMMISSION_PERCENT / 100));
    return round($net, 2);
}
