# Magento 2 - Cloudflare Module

## Overview

A Magento 2 module that purges the website's Cloudflare cache when flushing the cache storage in the Magento admin.

## Requirements

Magento Open Source (CE) Version 2.1.x, 2.2.x

## Installation

Include the package.

```bash
$ composer require sussexdev/module-cloudflare
```

Enable the module.

```bash
$ php bin/magento module:enable SussexDev_Cloudflare
$ php bin/magento setup:upgrade
$ php bin/magento cache:clean
```

## Usage

Head to ```Stores -> Configuration -> Advanced -> Developer -> Purge Cloudflare Cache``` to enable the module and add your Cloudflare credentials.

Within ```System -> Cache Management```, click on ```Flush Cache Storage```.

