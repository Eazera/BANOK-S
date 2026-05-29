# Banoks POS System

Customized WordPress POS system developed by the Eazera Team for Banoks operations.

## Overview

Banoks POS System is a WordPress plugin for managing in-store sales, online orders, inventory, expenses, cash balances, delivery areas, and business reports from one admin workflow.

Current plugin version: `1.6.8`

## Features

- Walk-in POS order processing
- Cash and GCash payment support
- Online ordering with cart and checkout flow
- Delivery and pickup fulfillment options
- Online order notifications and status management
- GCash payment proof review
- Product management with categories, images, prices, and availability
- Delivery area management with delivery fees
- Stock and inventory management
- Low-stock alerts and stock movement tracking
- Owner dashboard for operations overview
- Cash management for store cash, cash on hand, GCash balance, and bank balance
- Expense tracking by branch and cash source
- Business reports for walk-in and online sales
- PDF report export
- Cashier role support with restricted POS access

## Requirements

- WordPress installation
- PHP version supported by the target WordPress site
- Administrator access for installation and setup

## Installation

1. Prepare the plugin ZIP file.
2. In WordPress Admin, go to `Plugins > Add New > Upload Plugin`.
3. Upload the plugin ZIP.
4. Activate `Banoks POS System`.
5. Open the `Banoks POS` menu in the WordPress dashboard.

The plugin creates and updates its required database tables during activation and admin use.

## Manual Updates

This plugin is intended to be updated manually by uploading a new ZIP file in WordPress.

To publish a manual update:

1. Update the plugin version in `banoks-pos-system.php`.
2. Update `BANOKS_POS_VERSION` in the same file.
3. Compress the plugin folder into a ZIP file.
4. Upload the ZIP through `Plugins > Add New > Upload Plugin`.
5. Choose `Replace current with uploaded` when WordPress asks.

## Release Notes

See [RELEASE_NOTES.md](RELEASE_NOTES.md) for the current release summary.

## Important Notes

- Back up the WordPress database before installing or updating on a live site.
- Test updates on a staging site before production deployment.
- Keep the plugin version updated before preparing a new ZIP.

## Author

Eazera  
https://Eazera.ph
