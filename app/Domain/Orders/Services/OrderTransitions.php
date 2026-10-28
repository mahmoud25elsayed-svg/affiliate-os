<?php

declare(strict_types=1);

namespace App\Domain\Orders\Services;

use App\Domain\Orders\DTOs\OrderActor;
use App\Domain\Orders\Enums\OrderStatus;

class OrderTransitions
{
    /**
     * Complete matrix of allowed transitions and their rules.
     *
     * @var array<string, array<string, array{requires_reason: bool, allowed_actors: array<string>, allowed_roles?: array<string>}>>
     */
    protected static array $matrix = [
        OrderStatus::SUBMITTED->value => [
            OrderStatus::REJECTED_QUALITY->value => [
                'requires_reason' => true,
                'allowed_actors' => ['system', 'staff'],
                'allowed_roles' => ['owner', 'manager', 'orders', 'support'],
            ],
            OrderStatus::PENDING_CONFIRMATION->value => [
                'requires_reason' => false,
                'allowed_actors' => ['system', 'staff'],
                'allowed_roles' => ['owner', 'manager', 'orders', 'call_center'],
            ],
            OrderStatus::CANCELLED_BY_CUSTOMER->value => [
                'requires_reason' => true,
                'allowed_actors' => ['system', 'staff', 'customer', 'affiliate'],
                'allowed_roles' => ['owner', 'manager', 'orders', 'support', 'call_center'],
            ],
        ],

        OrderStatus::PENDING_CONFIRMATION->value => [
            OrderStatus::CONFIRMED->value => [
                'requires_reason' => false,
                'allowed_actors' => ['system', 'staff', 'customer'],
                'allowed_roles' => ['owner', 'manager', 'orders', 'call_center'],
            ],
            OrderStatus::UNREACHABLE->value => [
                'requires_reason' => true,
                'allowed_actors' => ['system', 'staff'],
                'allowed_roles' => ['owner', 'manager', 'call_center', 'orders'],
            ],
            OrderStatus::CANCELLED_BY_CUSTOMER->value => [
                'requires_reason' => true,
                'allowed_actors' => ['system', 'staff', 'customer', 'affiliate'],
                'allowed_roles' => ['owner', 'manager', 'call_center', 'orders', 'support'],
            ],
        ],

        OrderStatus::UNREACHABLE->value => [
            OrderStatus::PENDING_CONFIRMATION->value => [
                'requires_reason' => false,
                'allowed_actors' => ['system', 'staff', 'customer', 'affiliate'],
                'allowed_roles' => ['owner', 'manager', 'call_center', 'orders'],
            ],
            OrderStatus::CANCELLED_NO_ANSWER->value => [
                'requires_reason' => true,
                'allowed_actors' => ['system', 'staff'],
                'allowed_roles' => ['owner', 'manager', 'call_center', 'orders'],
            ],
        ],

        OrderStatus::CONFIRMED->value => [
            OrderStatus::RESERVED->value => [
                'requires_reason' => false,
                'allowed_actors' => ['system', 'staff'],
                'allowed_roles' => ['owner', 'manager', 'orders', 'catalog'],
            ],
            OrderStatus::OUT_OF_STOCK->value => [
                'requires_reason' => true,
                'allowed_actors' => ['system', 'staff'],
                'allowed_roles' => ['owner', 'manager', 'orders', 'catalog'],
            ],
            OrderStatus::CANCELLED_BY_CUSTOMER->value => [
                'requires_reason' => true,
                'allowed_actors' => ['system', 'staff', 'customer', 'affiliate'],
                'allowed_roles' => ['owner', 'manager', 'orders', 'call_center', 'support'],
            ],
        ],

        OrderStatus::RESERVED->value => [
            OrderStatus::OUT_OF_STOCK->value => [
                'requires_reason' => true,
                'allowed_actors' => ['system', 'staff'],
                'allowed_roles' => ['owner', 'manager', 'orders', 'catalog'],
            ],
            OrderStatus::PACKED->value => [
                'requires_reason' => false,
                'allowed_actors' => ['system', 'staff'],
                'allowed_roles' => ['owner', 'manager', 'orders'],
            ],
            OrderStatus::CANCELLED_BY_CUSTOMER->value => [
                'requires_reason' => true,
                'allowed_actors' => ['system', 'staff', 'customer', 'affiliate'],
                'allowed_roles' => ['owner', 'manager', 'orders', 'support'],
            ],
        ],

        OrderStatus::PACKED->value => [
            OrderStatus::SHIPPED->value => [
                'requires_reason' => false,
                'allowed_actors' => ['system', 'staff'],
                'allowed_roles' => ['owner', 'manager', 'orders'],
            ],
        ],

        OrderStatus::SHIPPED->value => [
            OrderStatus::OUT_FOR_DELIVERY->value => [
                'requires_reason' => false,
                'allowed_actors' => ['system', 'staff'],
                'allowed_roles' => ['owner', 'manager', 'orders'],
            ],
            OrderStatus::RETURNING->value => [
                'requires_reason' => true,
                'allowed_actors' => ['system', 'staff'],
                'allowed_roles' => ['owner', 'manager', 'orders', 'support'],
            ],
        ],

        OrderStatus::OUT_FOR_DELIVERY->value => [
            OrderStatus::DELIVERED->value => [
                'requires_reason' => false,
                'allowed_actors' => ['system', 'staff'],
                'allowed_roles' => ['owner', 'manager', 'orders'],
            ],
            OrderStatus::DELIVERY_FAILED->value => [
                'requires_reason' => true,
                'allowed_actors' => ['system', 'staff'],
                'allowed_roles' => ['owner', 'manager', 'orders'],
            ],
        ],

        OrderStatus::DELIVERY_FAILED->value => [
            OrderStatus::OUT_FOR_DELIVERY->value => [
                'requires_reason' => false,
                'allowed_actors' => ['system', 'staff'],
                'allowed_roles' => ['owner', 'manager', 'orders'],
            ],
            OrderStatus::RETURNING->value => [
                'requires_reason' => true,
                'allowed_actors' => ['system', 'staff'],
                'allowed_roles' => ['owner', 'manager', 'orders', 'support'],
            ],
        ],

        OrderStatus::DELIVERED->value => [
            OrderStatus::RETURN_REQUESTED->value => [
                'requires_reason' => true,
                'allowed_actors' => ['system', 'staff', 'customer'],
                'allowed_roles' => ['owner', 'manager', 'orders', 'support'],
            ],
            OrderStatus::SETTLED->value => [
                'requires_reason' => false,
                'allowed_actors' => ['system', 'staff'],
                'allowed_roles' => ['owner', 'manager', 'finance'],
            ],
        ],

        OrderStatus::RETURN_REQUESTED->value => [
            OrderStatus::RETURNING->value => [
                'requires_reason' => false,
                'allowed_actors' => ['system', 'staff'],
                'allowed_roles' => ['owner', 'manager', 'orders', 'support'],
            ],
        ],

        OrderStatus::RETURNING->value => [
            OrderStatus::RETURNED->value => [
                'requires_reason' => false,
                'allowed_actors' => ['system', 'staff'],
                'allowed_roles' => ['owner', 'manager', 'orders'],
            ],
        ],
    ];

    /**
     * Determine if a transition from one state to another is defined in the state machine.
     */
    public static function isAllowed(OrderStatus $from, OrderStatus $to): bool
    {
        return isset(self::$matrix[$from->value][$to->value]);
    }

    /**
     * Check if a transition is valid and authorized for the given actor.
     */
    public static function canTransition(OrderStatus $from, OrderStatus $to, ?OrderActor $actor = null): bool
    {
        if (! self::isAllowed($from, $to)) {
            return false;
        }

        if ($actor === null) {
            return true;
        }

        $rule = self::$matrix[$from->value][$to->value];

        // Actor type check
        if (! in_array($actor->type, $rule['allowed_actors'], true)) {
            return false;
        }

        // Staff role check
        if ($actor->isStaff() && isset($rule['allowed_roles'])) {
            return $actor->hasRole(...$rule['allowed_roles']);
        }

        return true;
    }

    /**
     * Check whether a specific transition requires a ReasonCode.
     */
    public static function requiresReason(OrderStatus $from, OrderStatus $to): bool
    {
        if (! self::isAllowed($from, $to)) {
            return false;
        }

        return self::$matrix[$from->value][$to->value]['requires_reason'] ?? false;
    }

    /**
     * Get all target OrderStatuses that can be transitioned to from the given current status.
     *
     * @return array<OrderStatus>
     */
    public static function getAllowedTransitions(OrderStatus $from, ?OrderActor $actor = null): array
    {
        if (! isset(self::$matrix[$from->value])) {
            return [];
        }

        $targets = [];
        foreach (self::$matrix[$from->value] as $toValue => $rule) {
            $toStatus = OrderStatus::from($toValue);
            if ($actor === null || self::canTransition($from, $toStatus, $actor)) {
                $targets[] = $toStatus;
            }
        }

        return $targets;
    }

    /**
     * Get raw transition matrix.
     *
     * @return array<string, array<string, array{requires_reason: bool, allowed_actors: array<string>, allowed_roles?: array<string>}>>
     */
    public static function getMatrix(): array
    {
        return self::$matrix;
    }
}
