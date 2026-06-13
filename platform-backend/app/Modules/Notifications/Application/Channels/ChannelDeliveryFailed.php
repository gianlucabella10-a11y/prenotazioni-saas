<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Channels;

use RuntimeException;

final class ChannelDeliveryFailed extends RuntimeException
{
}
