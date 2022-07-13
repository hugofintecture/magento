# Fintecture Payment module for Magento 2.4

[![Latest Stable Version](http://poser.pugx.org/fintecture/payment/v)](https://packagist.org/packages/fintecture/payment) [![Total Downloads](http://poser.pugx.org/fintecture/payment/downloads)](https://packagist.org/packages/fintecture/payment) [![Monthly Downloads](http://poser.pugx.org/fintecture/fintecture-sdk-php/d/monthly)](https://packagist.org/packages/fintecture/fintecture-sdk-php) [![License](http://poser.pugx.org/fintecture/payment/license)](https://packagist.org/packages/fintecture/payment) [![PHP Version Require](http://poser.pugx.org/fintecture/payment/require/php)](https://packagist.org/packages/fintecture/payment)

Fintecture is a Fintech that has a payment solution via bank transfer available at https://www.fintecture.com.

Email to developer@fintecture.com to get the full API documentation.

## Requirements

- Magento 2.4.0 at least
- PHP 7.3 or greater

More information on [Magento documentation](https://devdocs.magento.com/guides/v2.4/install-gde/system-requirements.html).

## Installation

### Composer (recommended)

`composer require fintecture/payment`

### FTP

- Connect to your server (FTP/SSH...)
- Browse to /var/www/html (depending on which path you have installed Magento 2)
- Browse to app/code
- Create folders Fintecture/Payment
- Upload the code in there (final file structure : app/code/Fintecture/Payment/*)
- Don't forget to set the correct permission for files and folder specially /etc/lib/* should be writable
- Come back to Magento main installation path

## Activation

- Check Module status: `php bin/magento module:status Fintecture_Payment`
- Enable Fintecture Payment module: `php bin/magento module:enable Fintecture_Payment`
- Apply upgrade: `php bin/magento setup:upgrade`
- Deploy static content: `php bin/magento setup:static-content:deploy -f`
- Compile catalog: `php bin/magento setup:di:compile`
- Clean the cache: `php bin/magento cache:clean` or go to System > Tools > Cache Management and click Flush Static Files Cache.

## Configuration

Go to Stores > Configuration > Sales > Payment methods.

- Select environment (sandbox/production)
- Fill API key, API secret, API private key based on the selected environment (https://console.fintecture.com/)
- Select payment method display (show logo or not)
- Test your connection (if everything is ok you should have a green message)
- Don't forget to enable the payment method unless it won't be displayed in the front end

## Other Magento versions

- Magento 2.3 : https://github.com/Fintecture/magento/tree/magento2.3
- Magento 2.2 : Please contact us to get our plugin for this version.
- Magento 2.1 : Please contact us to get our plugin for this version.