📌 PROJECT CONTEXT
SP gatewayhub – Centralized Multi-Gateway Payment Collection Platform
1️⃣ Business Objective
SurePay aims to build a centralized payment collection platform that can be deployed to multiple client websites such as:
⦁	Matthews Foundation (Donation site)
⦁	Ride service apps
⦁	Churches
⦁	NGOs
⦁	LGUs
⦁	Online service providers
The goal is:
Allow each client website to accept digital payments using selected payment gateways (e.g., GCash, Maya, Coins QR, etc.), while:
⦁	All Coins QR transactions settle to SurePay’s bank account
⦁	SurePay redistributes funds to respective clients
⦁	Each client can enable only the gateways they want to use
2️⃣ Payment Gateway Model (New Enhancement)
The platform supports multiple payment gateways:
⦁	GCash
⦁	Maya
⦁	Coins.ph (Dynamic QR)
⦁	PayPal
⦁	QRPH
However:
Each client can choose which gateways are enabled for their organization.
Example:
Matthews Foundation wants:
⦁	✅ GCash
⦁	✅ Maya
⦁	❌ PayPal
⦁	❌ Coins Checkout
⦁	❌ QRPH Direct
Then:
Only GCash and Maya will appear on their payment page.
Other gateways remain disabled for them.
This gives:
⦁	Cost control
⦁	Cleaner checkout
⦁	Operational flexibility
⦁	Scalable architecture
3️⃣ Target Payment Flow (Fund Movement)
Example Scenario (Coins QR Enabled Client) Customer visits Matthews Foundation website. Customer selects payment. System generates a Coins dynamic QR. Customer pays using: GCash ⦁	Maya
⦁	Coins wallet
⦁	Other QRPH-compatible wallets Coins processes the payment. Coins settles funds to SurePay’s registered bank account. SurePay manually or internally transfers funds to Matthews Foundation’s bank. Important:
All Coins QR transactions pass through SurePay’s bank account first.
The system does not handle actual bank transfers.
It only records, tracks, and monitors payments.
4️⃣ System Role & Responsibility
🔹 What the System DOES
⦁	Generate Coins dynamic QR per transaction
⦁	Enable/disable payment gateways per client
⦁	Securely verify webhooks (HMAC validation)
⦁	Prevent replay attacks
⦁	Record payments in database
⦁	Associate each payment with correct client
⦁	Provide dashboards:
⦁	Admin (SurePay) → sees all clients & transactions
⦁	Client → sees only their own transactions
⦁	Provide reporting and monitoring tools
🔹 What the System DOES NOT DO
⦁	It does NOT directly receive funds
⦁	It does NOT act as a bank
⦁	It does NOT replace Coins.ph
⦁	It does NOT process wallet-to-bank settlements
⦁	It does NOT automatically disburse funds (current phase)
Coins processes payments.
SurePay receives settlement.
5️⃣ Multi-Client Structure (Multi-Tenant)
SurePay is the Platform Owner (Super Admin).
Each client:
⦁	Is registered as a separate entity
⦁	Has its own:
⦁	Public payment page
⦁	Gateway configuration
⦁	Transaction history
⦁	Cannot see other clients’ data
SurePay Admin:
⦁	Can see all clients
⦁	Can see total collections
⦁	Can filter per client
⦁	Can configure enabled gateways per client
⦁	Has centralized system control
6️⃣ Why This Architecture Is Needed
Without SurePay:
Each client would need:
⦁	Separate Coins integration
⦁	Separate webhook setup
⦁	Separate gateway configuration
⦁	Separate reporting tools
⦁	Separate technical maintenance
With SurePay:
⦁	One centralized integration (under SurePay)
⦁	One webhook endpoint
⦁	Multi-client structure
⦁	Gateway activation per client
⦁	Simplified operations
⦁	Scalable onboarding of new clients
7️⃣ Technical Architecture (Unified Model)
Core Components Gateway Abstraction Layer Client Gateway Configuration Table Transaction Tracking Engine Webhook Verification Engine Admin Dashboard Client Dashboard 🔹 Gateway Configuration Logic
Example database table:
client_gateways
⦁	id
⦁	client_id
⦁	gateway_name (gcash, maya, coins, paypal, qrph)
⦁	is_enabled (true/false)
⦁	created_at
Checkout logic:
Fetch client enabled gateways
Display only enabled gateways
8️⃣ Technical Scope (Current Phase)
Included:
⦁	Coins Dynamic QR Integration
⦁	Secure Webhook Verification (HMAC + signature validation)
⦁	Multi-client tracking
⦁	Per-client gateway enable/disable logic
⦁	Admin dashboard
⦁	Client dashboard
⦁	VPS deployment with public webhook endpoint
Not Included (Future Phase):
⦁	Subscription billing
⦁	Automated fund settlement
⦁	Advanced gateway routing logic
⦁	Marketplace split payments
⦁	Escrow functionality
9️⃣ Business Positioning (Important)
SurePay is:
A centralized payment orchestration and monitoring platform.
It is NOT:
⦁	A bank
⦁	A wallet
⦁	A remittance provider
⦁	A financial institution
⦁	A direct payment processor
Coins and other gateways handle financial movement.
SurePay handles orchestration and visibility.
🔟 Final Simplified Summary
SurePay is a centralized multi-client payment collection platform where:
⦁	Multiple organizations can accept digital payments.
⦁	Each client can enable only the payment gateways they need.
⦁	Coins QR payments settle first to SurePay’s bank account.
⦁	SurePay redistributes funds to respective clients.
⦁	The system tracks, verifies, and reports all transactions securely.
Buddy this is now:
✔ Centralized Aggregator Model
✔ Multi-Gateway SaaS Model
✔ Configurable Per Client
✔ Scalable
✔ Legally safer positioning
✔ Clean architecture