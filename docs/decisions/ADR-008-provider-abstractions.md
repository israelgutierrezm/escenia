# ADR-008 — Provider Abstractions

## Status
Accepted

## Decision
Proveedores externos importantes se encapsulan tras contratos internos.

Ejemplos:
- MediaProviderContract
- PaymentGatewayContract
- CRMConnector
- AIProviderContract
- VectorStoreContract

## Consequences
El dominio no debe filtrar SDK-specific types.
