# GatewayHub Platform Documentation

Last updated: February 28, 2026

## 1. Platform Identity

GatewayHub (SurePay) is a centralized, multi-client payment orchestration and monitoring platform.

GatewayHub is not a bank, wallet, remittance provider, or direct payment processor.

## 2. Business Objective

The platform allows multiple client organizations (merchants) to collect digital payments through one centralized SurePay-managed payment infrastructure.

Examples of client organizations:
- Foundations
- Ride service platforms
- Churches
- NGOs
- LGUs
- Online service providers

## 3. Gateway Model (Current)

The platform uses Coins.ph dynamic QR as the processing rail for checkout.

Customer-facing payment options that may appear in checkout:
- GCash
- Maya
- Coins wallet
- PayPal
- QRPH-compatible wallets (including PayQRPH/QRPH option)

Important:
- These options are orchestrated through the Coins dynamic QR flow in the current model.
- Gateway availability is configurable per merchant (on/off).
- Platform credentials are managed centrally by SurePay (not by each merchant).

## 4. Payment and Fund Movement

1. Customer opens a merchant payment page.
2. Customer selects a payment option shown for that merchant.
3. System generates a Coins dynamic QR transaction.
4. Customer pays via a supported wallet/app.
5. Coins processes the payment.
6. Settlement goes to SurePay's registered bank account.
7. SurePay transfers funds to the merchant outside this system process.

## 5. What the System Does

- Creates and tracks payment records per merchant.
- Generates dynamic QR checkout via Coins integration.
- Verifies gateway webhooks securely.
- Prevents invalid or replay webhook processing.
- Stores transaction status and reference history.
- Provides merchant and admin dashboards.
- Provides filtering, export, reporting, and monitoring.
- Allows per-merchant gateway activation/deactivation.

## 6. What the System Does Not Do

- Does not directly hold customer funds.
- Does not execute automatic bank disbursement in current phase.
- Does not replace gateway providers.
- Does not act as a financial institution.

## 7. Multi-Tenant Model

### SurePay Admin

- Can view all merchants.
- Can view total collections across merchants.
- Can filter total collections by merchant.
- Can configure enabled gateways per merchant.
- Can manage centralized gateway configuration.

### Merchant

- Can view only their own transactions.
- Can view collections and payment history for their account.
- Can toggle allowed gateway options for their own checkout (subject to global platform availability).
- Cannot access other merchants' data.

## 8. Core Functional Scope (Current Phase)

- Coins dynamic QR payment creation.
- Webhook validation and status updates.
- Merchant gateway toggle management.
- Admin and merchant dashboards.
- Transaction export and reporting tools.
- Production deployment support with public webhook endpoint.

## 9. Out of Scope (Future Phases)

- Subscription billing.
- Automated settlement/disbursement.
- Advanced smart routing across processors.
- Marketplace split payments.
- Escrow flows.

## 10. Data and Configuration Principles

- Payment data is merchant-scoped.
- Gateway enablement is merchant-configurable, with platform-level controls.
- Credentials are centralized to SurePay platform ownership.
- Settlement tracking is ledger/reporting-oriented in current phase.

## 11. Compliance and Positioning Notes

- GatewayHub is positioned as an orchestration, visibility, and operations platform.
- Financial movement is handled by gateway providers and external settlement operations.
- Language in UI and APIs should avoid implying direct custodial banking functions by GatewayHub.
