# Fintecture Payment module for magento 2-3, 2.4
Fintecture is a Fintech that has a payment solution via bank transfer available at [https://www.fintecture.com/]. 

Email to developer@fintecture.com to get the full API documentation
**Requirements**

- PHP 7.1 or greater with extensions 'json', 'openssl' activated
- Magento 2.3 or 2.4

**Installation**

`composer require fintecture/payment`

- Check Module status : `sudo php bin/magento module:status Fintecture_Payment`
- Enable Fintecture Payment module : `sudo php bin/magento module:enable Fintecture_Payment`
- Apply upgrade : `sudo php bin/magento setup:upgrade`
- Deploy static content : `sudo php bin/magento setup:static-content:deploy -f`
- Compile catalog : `sudo php bin/magento setup:di:compile`
- Clean the cache : `sudo php bin/magento cache:clean` or Go to System > Tools > Cache Management and click Flush Static
  Files Cache.

**Configuration**

Go to System > Store > Configuration > Sales > Payment methods.

- Select environment (sandbox/production)
- Fill API key, Api secret , Api private key based on the selected environment (https://console.fintecture.com/)
- Select payment method display (Logo type, Logo position)
- Test your connection (if everything is ok you should have a green message)
- Don't forget to enable the payment method unless it won't be displayed in the front end
