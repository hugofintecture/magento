<?php

declare(strict_types=1);

namespace Fintecture\Payment\Setup\Patch\Data;

use Magento\Cms\Model\Block;
use Magento\Cms\Model\BlockFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;
use Magento\Store\Model\Store;

class AddCheckoutPaymentBlocks implements DataPatchInterface, PatchVersionInterface
{
    /** @var ModuleDataSetupInterface */
    private $moduleDataSetup;

    /** @var BlockFactory */
    private $blockFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        BlockFactory $blockFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->blockFactory = $blockFactory;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public static function getVersion(): string
    {
        return '2.0.0';
    }

    public function apply()
    {
        $contentFr = '<div class="checkout_block payment-method-content">
            <p id="f-description"><span style="vertical-align: inherit;">Vous serez automatiquement redirigé vers l’espace sécurisé de votre banque pour confirmer le paiement. <b>Payez vos achats instantanément et en toute sécurité.</b></span></p>

            <p id="f-howto">Comment ça marche ?</p>

            <div id="f-steps">
                <div class="f-step-wrapper">
                    <div class="f-step-icon f-step-1-icon">
                        <div class="f-step-img f-step-1-img"></div>
                    </div>
                    <p>Sélectionnez votre banque</p>
                </div>
                <div class="f-step-wrapper">
                    <div class="f-step-icon f-step-2-icon">
                        <div class="f-step-img f-step-2-img"></div>
                    </div>
                    <p>Connectez-vous avec vos identifiants bancaires habituels</p>
                </div>
                <div class="f-step-wrapper">
                    <div class="f-step-icon f-step-3-icon">
                        <div class="f-step-img f-step-3-img"></div>
                    </div>
                    <p>Validez la transaction de manière sécurisée</p>
                </div>
                <div class="f-step-wrapper">
                    <div class="f-step-icon f-step-4-icon">
                        <div class="f-step-img f-step-4-img"></div>
                    </div>
                    <p>Votre achat est confirmé !</p>
                </div>
            </div>

            <!--<div class="term_cond"><input name="" type="checkbox"><span style="vertical-align: inherit;"><span style="vertical-align: inherit;">Click on your bank app notification. </span><span style="vertical-align: inherit;">Confirm payment</span></span></div>-->
        </div>';

        $contentEn = '<div class="checkout_block payment-method-content">
            <p id="f-description"><span style="vertical-align: inherit;">You will be automatically redirected to your secured bank environment to confirm your payment. <b>Pay for your purchases instantly and securely.</b></span></p>

            <p id="f-howto">How it works?</p>

            <div id="f-steps">
                <div class="f-step-wrapper">
                    <div class="f-step-icon f-step-1-icon">
                        <div class="f-step-img f-step-1-img"></div>
                    </div>
                    <p>Select your bank</p>
                </div>
                <div class="f-step-wrapper">
                    <div class="f-step-icon f-step-2-icon">
                        <div class="f-step-img f-step-2-img"></div>
                    </div>
                    <p>Login with your usual banking ID’s</p>
                </div>
                <div class="f-step-wrapper">
                    <div class="f-step-icon f-step-3-icon">
                        <div class="f-step-img f-step-3-img"></div>
                    </div>
                    <p>Confirm your payment securely</p>
                </div>
                <div class="f-step-wrapper">
                    <div class="f-step-icon f-step-4-icon">
                        <div class="f-step-img f-step-4-img"></div>
                    </div>
                    <p>Your purchase is confirmed!</p>
                </div>
            </div>

            <!--<div class="term_cond"><input name="" type="checkbox"><span style="vertical-align: inherit;"><span style="vertical-align: inherit;">Click on your bank app notification. </span><span style="vertical-align: inherit;">Confirm payment</span></span></div>-->
        </div>';
        $blockFr = [
            'title' => 'Checkout Payment Description FR',
            'identifier' => 'checkout_payment_block',
            'content' => $contentFr,
            'is_active' => 1,
            'stores' => Store::DEFAULT_STORE_ID
        ];

        $blockEn = [
            'title' => 'Checkout Payment Description EN',
            'identifier' => 'checkout_payment_block_en',
            'content' => $contentEn,
            'is_active' => 1,
            'stores' => Store::DEFAULT_STORE_ID
        ];

        $this->moduleDataSetup->startSetup();

        /** @var Block $block */
        $block = $this->blockFactory->create();
        $block->setData($blockFr)->save();
        $block->setData($blockEn)->save();

        $this->moduleDataSetup->endSetup();
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases(): array
    {
        return [];
    }
}
