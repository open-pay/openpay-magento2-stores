Openpay-Magento2-Stores
======================

Openpay payment gateway extension for Magento2 (v2.2.5)


Install
=======

1. Go to Magento2 root folder.

2. Enter following commands to install module:

    ```bash    
    composer require openpay/magento2-stores dev-master
    ```
   Wait while dependencies are updated.

3. Enter following commands to enable module:

    ```bash
    php bin/magento module:enable Openpay_Stores --clear-static-content
    php bin/magento setup:upgrade
    php bin/magento cache:clean
    ```

4. Enable and configure Openpay in Magento Admin under Stores > Configuration > Sales > Payment Methods > Openpay (Cash payment)


