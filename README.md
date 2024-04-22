# Official KohortPay WooCommerce module

This module adds a new payment method to WooCommerce: `KohortPay`, which allows you to lower your acquisition costs by turning your customers into your brands advocates. They will have the opportunity to invite all their friends to buy more on your store in exchange for cashback.


## Table of content

- [Official KohortPay WooCommerce module](#official-kohortpay-woocommerce-module)
  - [Table of content](#table-of-content)
  - [What is KohortPay](#what-is-kohortpay)
    - [Description](#description)
    - [Benefits](#benefits)
  - [Module overview](#module-overview)
    - [Version](#version)
    - [Licence](#licence)
    - [Compabilities and Restrictions](#compabilities-and-restrictions)
    - [Features](#features)
  - [Installation](#installation)
    - [Using ZIP](#using-zip)
    - [Using Wordpress marketplace](#using-wordpress-marketplace)
    - [Using CLI](#using-cli)
    - [Using Composer](#using-composer)
  - [Configuration](#configuration)
    - [Prerequisites](#prerequisites)
    - [Activation](#activation)
    - [API Secret Key](#api-secret-key)
    - [Minimum amount](#minimum-amount)
  - [Demo](#demo)
  - [Testing](#testing)
    - [Requirements](#requirements)
    - [Starting WooCommerce](#starting-woocommerce)
    - [Testing the module](#testing-the-module)
    - [Stopping WooCommerce](#stopping-woocommerce)
  - [Need help](#need-help)
    - [Documentation](#documentation)
    - [Feedback](#feedback)
    - [Support](#support)

## What is KohortPay

### Description

KohortPay lets your customers pay, refer and save on every purchase. Cut your customer acquisition costs in half while offering your customers a social and fun brand experience. And just like that, your checkout becomes so koool.

### Benefits

- **No setup costs**: integrate KohortPay on your site and increase customer satisfaction in 10 minutes - ready, set, GO.
- **Lower your acquisition costs**: drive high-quality customer acquisition at half the cost of existing overpriced customer acquisition channels.
- **Pay for performance**: no commitments. You only pay for results. Start and stop in 1 click.
- **Brand reinforcement**: generate content from customers and harness the power of  word-of-mouth recommendations. Personalize KohortPay to look and feel like your brand. Configure the experience to overcome your challenges and meet your objectives.

## Module overview

### Version
Current version is 1.1.0. See all [releases here](https://github.com/kohortpay/module-woocommerce/releases).

### Licence
The module and this repository is under MIT License. 

### Compabilities and Restrictions
- Only FR and EN languages available.
- Only EUR currency available.
- Works and has been tested with WooCommerce 8.2 (should work with other WooCommerce versions but not tested, use at your own risk).
- You should use only 2 decimals for your price.

### Features
- Add a new payment method that you customer will love (Pay less, together)
- Redirect to an awesome and customized payment page (using you customer cart details).
- Enable/disable the module by a simple switch through the settings.
- Possibility to set minimun amount, under which the payment is disabled. 
- Easy way to switch live/test mode by filling you API secret key (sk or sk_test).
- Handle API errors (with more details if WooCommerce is in debug mode).

## Installation

### Using ZIP

1. Log into your WooCommerce admin
2. Go to Plugins > Installed Plugins
3. Click on the button top-lest "`Add New Plugin`" and select the ZIP from this repository `./kohortpay.zip` 
4. After installation is done, you can configure it ([see instructions below](#configuration))

### Using Wordpress marketplace

Coming soon...

### Using CLI

Coming soon...

### Using Composer

Coming soon...

## Configuration

### Prerequisites
- You should have a KohortPay account. If it's not the case, you can [register here](https://dashboard.kohortpay.com/sign-up).
- You should have installed the module on your WooCommerce instance and have access to its settings page (WooCommerce > Settings > Payments).

### Activation

You can display or hide the KohortPay payment method from you checkout page using this configuration (enabled/disabled).

### API Secret Key

Found in KohortPay Dashboard > Developer settings > API Keys.
Start with sk_ or sk_test (for test mode).

### Minimum amount

You can define here the total order minimum amount to display the KohortPay payment method (minimum 30€).

## Demo

You can access a live demo of the KohortPay module here (WooCommerce 8.1.0):
- Front-Office : [https://woocommerce-demo.kohortpay.com](https://woocommerce-demo.kohortpay.com)
- Back-Office : [https://woocommerce-demo.kohortpay.com/wp-admin](https://WooCommerce-demo.kohortpay.com/wp-admin)
    - Login : admin3218
    - Password : Gfxq8NE2ATXC

## Testing

If you want to test the KohortPay module on a fresh WooCommerce installation, please read the following instruction.
The stack is based on Bitnami Docker image.

### Requirements

- Git ([Install instruction](https://git-scm.com/book/en/v2/Getting-Started-Installing-Git))
- Docker desktop:
  - [Mac install instruction](https://docs.docker.com/desktop/install/mac-install/)
  - [Windows install instruction](https://docs.docker.com/desktop/install/windows-install/)
- Docker Compose: Already included in Docker Desktop.

### Starting WooCommerce

1. Start your Docker desktop application
2. Clone the repository and go inside the directory:
   ```
   git clone git@github.com:kohortpay/module-woocommerce.git
   cd module-woocommerce
   ```
3. Start the docker stack:
   ```
   docker-compose up -d --build
   ```
4. Wait for container to be up (~1 minute), then visit http://localhost/
5. Your WooCommerce is UP. Enjoy!

### Testing the module

1.  Go to WooCommerce admin: http://localhost/wp-admin/
2.  Log in with these credentials:
    - Login: admin
    - Password: admin
3. Install the module ([see instruction above](#installation))
4. Configure the module ([see instructions above](#configuration))
5. Go back to the frontend (http://localhost/) and proceed to the checkout with enough products in your cart (to reach minimum amount defined in settings).
6. At the Step 4, select KohortPay as a payment method and place the order. You should be redirected to KohortPay payment page. Enjoy!

### Stopping WooCommerce

When your tests are over, you can stop and destroy everything with the following command:

```
docker-compose down --volumes
```

## Need help

### Documentation
If you have any questions, do not hesitate to check our documentations : 
- [Product Docs](https://docs.kohortpay.com/)
- [API & SDK Reference](https://api-docs.kohortpay.com/)
- [Help Center](https://help.kohortpay.com/fr)

### Feedback
If you have any idea or suggestion to improve our solution or the module, you can send an email to feedback@kohortpay.com. You can also check our [roadmap here](https://roadmap.kohortpay.com/tabs/1-under-consideration).

### Support
If you need help, please contact our support team by sending an email to support@kohortpay.com.

**NB**: We don't provide any SLA on our support response time (best effort). 
