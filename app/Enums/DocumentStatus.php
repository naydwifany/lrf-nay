<?php
// app/Enums/DocumentStatus.php

namespace App\Enums;

enum DocumentStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case PENDING_SUPERVISOR = 'pending_supervisor';
    case PENDING_GM = 'pending_gm';
    case PENDING_LEGAL = 'pending_legal';
    case PENDING_FINANCE = 'pending_finance';
    case IN_DISCUSSION = 'discussion';
    case AGREEMENT_CREATION = 'agreement_creation';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';

    public function getLabel(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Submitted',
            self::PENDING_SUPERVISOR => 'Pending Supervisor',
            self::PENDING_GM => 'Pending General Manager',
            self::PENDING_LEGAL => 'Pending Legal Review',
            self::PENDING_FINANCE => 'Pending Finance Review',
            self::IN_DISCUSSION => 'In Discussion',
            self::AGREEMENT_CREATION => 'Agreement Creation',
            self::COMPLETED => 'Completed',
            self::REJECTED => 'Rejected',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::DRAFT => 'gray',
            self::SUBMITTED => 'info',
            self::PENDING_SUPERVISOR => 'warning',
            self::PENDING_GM => 'primary',
            self::PENDING_LEGAL => 'secondary',
            self::PENDING_FINANCE => 'info',
            self::IN_DISCUSSION => 'success',
            self::AGREEMENT_CREATION => 'primary',
            self::COMPLETED => 'success',
            self::REJECTED => 'danger',
        };
    }

    public static function getOptions(): array
    {
        return [
            self::DRAFT->value => self::DRAFT->getLabel(),
            self::SUBMITTED->value => self::SUBMITTED->getLabel(),
            self::PENDING_SUPERVISOR->value => self::PENDING_SUPERVISOR->getLabel(),
            self::PENDING_GM->value => self::PENDING_GM->getLabel(),
            self::PENDING_LEGAL->value => self::PENDING_LEGAL->getLabel(),
            self::PENDING_FINANCE->value => self::PENDING_FINANCE->getLabel(),
            self::IN_DISCUSSION->value => self::IN_DISCUSSION->getLabel(),
            self::AGREEMENT_CREATION->value => self::AGREEMENT_CREATION->getLabel(),
            self::COMPLETED->value => self::COMPLETED->getLabel(),
            self::REJECTED->value => self::REJECTED->getLabel(),
        ];
    }

    public static function getColors(): array
    {
        return [
            self::DRAFT->value => self::DRAFT->getColor(),
            self::SUBMITTED->value => self::SUBMITTED->getColor(),
            self::PENDING_SUPERVISOR->value => self::PENDING_SUPERVISOR->getColor(),
            self::PENDING_GM->value => self::PENDING_GM->getColor(),
            self::PENDING_LEGAL->value => self::PENDING_LEGAL->getColor(),
            self::PENDING_FINANCE->value => self::PENDING_FINANCE->getColor(),
            self::IN_DISCUSSION->value => self::IN_DISCUSSION->getColor(),
            self::AGREEMENT_CREATION->value => self::AGREEMENT_CREATION->getColor(),
            self::COMPLETED->value => self::COMPLETED->getColor(),
            self::REJECTED->value => self::REJECTED->getColor(),
        ];
    }
}