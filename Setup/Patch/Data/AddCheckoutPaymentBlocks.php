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
    )
    {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->blockFactory    = $blockFactory;
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
        $contentFr = '
                <div class="chekout_block payment-method-content">
                    <p class="c_para-1"><span style="vertical-align: inherit;"><span style="vertical-align: inherit;">Payer par Virement Instantané est une solution de paiement autorisée par<img
                        src="{{view url=\'Fintecture_Payment::images/Banque_de_France_logo_300x110.png\'}}" alt=""></span></span></p>
                    <p class="c_para-2"><span style="vertical-align: inherit;"><span style="vertical-align: inherit;">Payer vos achats, instantanément, en toute sécurité, en utilisant vos identifiants bancaires habituels - pas d’inscription nécessaire - </span></span>
                    </p>
                    <ul class="checkout_list">
                        <li><img src="{{view url=\'Fintecture_Payment::images/check_symbol.png\'}}" alt=""><span
                            style="vertical-align: inherit;"><span style="vertical-align: inherit;"> Sans saisir d’IBAN</span></span>
                        </li>
                        <li><img src="{{view url=\'Fintecture_Payment::images/check_symbol.png\'}}" alt=""><span
                            style="vertical-align: inherit;"><span style="vertical-align: inherit;">Sans ajouter de bénéficiaire de confiance</span></span>
                        </li>
                        <li><img src="{{view url=\'Fintecture_Payment::images/check_symbol.png\'}}" alt=""><span
                            style="vertical-align: inherit;"><span
                            style="vertical-align: inherit;"> Sans coût supplémentaire</span></span></li>
                    </ul>
                    <div>
                        <div class="chekout_icon_block">
                            <div class="chekcout_icon_img"><img src="{{view url=\'Fintecture_Payment::images/200_bank_icon.png\'}}" alt="">
                            </div>
                            <div class="checkout_icont_des">
                                <p class="p_blok"><span class="check_no">1.</span> Sélectionnez votre banque</p>
                            </div>
                        </div>
                        <div class="chekout_icon_block">
                            <div class="chekcout_icon_img"><img src="{{view url=\'Fintecture_Payment::images/200_lock.png\'}}" alt="">
                            </div>
                            <div class="checkout_icont_des">
                                <p class="p_blok"><span class="check_no">2.</span>Entrez vos identifiants de banque en ligne</p>
                            </div>
                        </div>
                        <div class="chekout_icon_block">
                            <div class="chekcout_icon_img"><img src="{{view url=\'Fintecture_Payment::images/200_mobile_phone.png\'}}"
                                                                alt=""></div>
                            <div class="checkout_icont_des">
                                <p class="p_blok"><span class="check_no">3.</span> Cliquez sur la notification de votre app bancaire.
                                    Confirmez le paiement</p>
                            </div>
                        </div>
                        <div class="chekout_icon_block">
                            <div class="chekcout_icon_img"><img
                                src="{{view url=\'Fintecture_Payment::images/shopping_bag_checkmark.png\'}}" alt=""></div>
                            <div class="checkout_icont_des">
                                <p><span style="vertical-align: inherit;"><span style="vertical-align: inherit;">Votre achat est confirmé!</span></span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <!--<div class="term_cond"><input name="" type="checkbox"><span style="vertical-align: inherit;"><span style="vertical-align: inherit;">Click on your bank app notification. </span><span style="vertical-align: inherit;">Confirm payment</span></span></div>-->
                </div>
                ';

        $contentEn = '
                <div class="chekout_block payment-method-content">
                    <p class="c_para-1">Pay with Instant Transfer is a payment solution authorized by<img
                        src="{{view url=\'Fintecture_Payment::images/Banque_de_France_logo_300x110.png\'}}" alt=""></p>
                    <p class="c_para-2">Pay for your purchases instantly and securely, using your online banking login and password - no
                        additional registration required -</p>
                    <ul class="checkout_list">
                        <li><img src="{{view url=\'Fintecture_Payment::images/check_symbol.png\'}}" alt=""> Without adding your IBAN</li>
                        <li><img src="{{view url=\'Fintecture_Payment::images/check_symbol.png\'}}" alt=""> Without adding a trusted
                            beneficiary
                        </li>
                        <li><img src="{{view url=\'Fintecture_Payment::images/check_symbol.png\'}}" alt=""> Without additional cost</li>
                    </ul>
                    <div>
                        <div class="chekout_icon_block">
                            <div class="chekcout_icon_img"><img src="{{view url=\'Fintecture_Payment::images/200_bank_icon.png\'}}" alt="">
                            </div>
                            <div class="checkout_icont_des">
                                <p><span class="check_no">1.</span> Select your bank</p>
                            </div>
                        </div>
                        <div class="chekout_icon_block">
                            <div class="chekcout_icon_img"><img src="{{view url=\'Fintecture_Payment::images/200_lock.png\'}}" alt="">
                            </div>
                            <div class="checkout_icont_des">
                                <p><span class="check_no">2.</span> Enter your online banking login and password</p>
                            </div>
                        </div>
                        <div class="chekout_icon_block">
                            <div class="chekcout_icon_img"><img src="{{view url=\'Fintecture_Payment::images/200_mobile_phone.png\'}}"
                                                                alt=""></div>
                            <div class="checkout_icont_des">
                                <p><span class="check_no">3.</span> Click on your bank app notification. Confirm payment</p>
                            </div>
                        </div>
                        <div class="chekout_icon_block">
                            <div class="chekcout_icon_img"><img
                                src="{{view url=\'Fintecture_Payment::images/shopping_bag_checkmark.png\'}}" alt=""></div>
                            <div class="checkout_icont_des">
                                <p>Your purchase is confirmed!</p>
                            </div>
                        </div>
                    </div>
                    <!--<div class="term_cond"><input name="" type="checkbox"> Click on your bank app notification. Confirm payment</div>-->
                </div>
                ';
        $blockFr   = [
            'title'      => 'Checkout Payment Description FR',
            'identifier' => 'checkout_payment_block',
            'content'    => $contentFr,
            'is_active'  => 1,
            'stores'     => Store::DEFAULT_STORE_ID
        ];

        $blockEn = [
            'title'      => 'Checkout Payment Description EN',
            'identifier' => 'checkout_payment_block_en',
            'content'    => $contentEn,
            'is_active'  => 1,
            'stores'     => Store::DEFAULT_STORE_ID
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
