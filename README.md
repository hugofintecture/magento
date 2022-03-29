# Fintecture Payment module for Magento 2.1

Fintecture is a Fintech that has a payment solution via bank transfer available at https://www.fintecture.com.

Email to developer@fintecture.com to get the full API documentation.

## Requirements

- Magento 2.1 at least
- PHP 7.0 with extensions **json**, **openssl** activated
- Fix curl bug (see section below)

## Installation

### Composer (recommended)

`composer require fintecture/payment`

### FTP

- Connect to your server (FTP/SSH...)
- Browse to /var/www/html (depending on which path you have installed Magento 2)
- Browse to app/code
- Create folder Fintecture
- Upload the code in there. (final file structure : app/code/Fintecture/Payment/*)
- Don't forget to set the correct permission for files and folder specially /etc/lib/* should be writable
- Come back to magento main installation path

## Activation

- Check Module status: `php bin/magento module:status Fintecture_Payment`
- Enable Fintecture Payment module: `php bin/magento module:enable Fintecture_Payment`
- Apply upgrade: `php bin/magento setup:upgrade`
- Deploy static content: `php bin/magento setup:static-content:deploy -f`
- Compile catalog: `php bin/magento setup:di:compile`
- Clean the cache: `php bin/magento cache:clean` or go to System > Tools > Cache Management and click Flush Static Files Cache

## Configuration

Go to Stores > Configuration > Sales > Payment methods.

- Select environment (sandbox/production)
- Fill API key, API secret, API private key based on the selected environment (https://console.fintecture.com/)
- Select payment method display (show logo or not)
- Test your connection (if everything is ok you should have a green message)
- Don't forget to enable the payment method unless it won't be displayed in the front end

## Fix curl bug

See https://github.com/magento/magento2/issues/19127.

Open `vendor/magento/framework/HTTP/Client/Curl.php` or `lib/internal/Magento/Framework/HTTP/Client/Curl.php` and change;

```php
protected function parseHeaders($ch, $data)
{
    if ($this->_headerCount == 0) {
        $line = explode(" ", trim($data), 3);
        if (count($line) != 3) {
            $this->doError("Invalid response line returned from server: " . $data);
        }
        $this->_responseStatus = intval($line[1]);
```

to
```php
protected function parseHeaders($ch, $data)
{
    if ($this->_headerCount == 0) {
        $line = explode(" ", trim($data), 3);
        if (count($line) < 2) {
            $this->doError("Invalid response line returned from server: " . $data);
        }
        $this->_responseStatus = (int)$line[1];
```
