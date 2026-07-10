<?php
/**
 * Hellio Messaging for Magento 2.
 *
 * @package   Hellio_Sms
 * @author    Hellio Solutions
 * @copyright Copyright (c) Hellio Solutions (https://helliomessaging.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 */

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Hellio_Sms',
    __DIR__
);
