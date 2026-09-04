<?php

namespace Libinkk\Permission\Authorization;

final class DecisionReason
{
    public const PERMISSION_MISSING = 'PERMISSION_MISSING';

    public const ROLE_MISSING = 'ROLE_MISSING';

    public const TENANT_MISMATCH = 'TENANT_MISMATCH';

    public const SCOPE_MISMATCH = 'SCOPE_MISMATCH';

    public const RESOURCE_DENIED = 'RESOURCE_DENIED';

    public const CONDITION_FAILED = 'CONDITION_FAILED';

    public const EXPIRED_PERMISSION = 'EXPIRED_PERMISSION';

    public const EXPLICIT_DENY = 'EXPLICIT_DENY';

    public const POLICY_DENIED = 'POLICY_DENIED';

    public const DELEGATION_EXPIRED = 'DELEGATION_EXPIRED';

    public const CONTEXT_MISSING = 'CONTEXT_MISSING';

    public const ALLOWED = 'ALLOWED';
}
