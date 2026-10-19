<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A mobile money payment could not be asked for. The message is written
 * for the person who pressed the button and is safe to show them.
 */
class PaymentRequestRefused extends RuntimeException {}
