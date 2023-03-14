<?php

declare(strict_types=1);

namespace Fintecture\Payment\Plugin;

use Fintecture\Payment\Model\Fintecture;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\CsrfValidator;
use Magento\Framework\App\RequestInterface;

class CsrfValidatorSkip
{
    public function aroundValidate(
        CsrfValidator $subject,
        \Closure $proceed,
        RequestInterface $request,
        ActionInterface $action
    ): void {
        if ($request->getModuleName() === Fintecture::CODE) {
            return;
        }

        $proceed($request, $action);
    }
}
