# Interoperabilidad SSO contra IdPs independientes

Corre Escenia de verdad (`php -S` sobre una SQLite aislada en esta carpeta, sin
tocar la base de desarrollo) frente a dos implementaciones de terceros:

- **OIDC**: [`oidc-provider`](https://github.com/panva/node-oidc-provider), un
  proveedor OpenID certificado. Ejercita descubrimiento, PKCE, `client_secret_basic`,
  firma por JWKS y los claims vía **UserInfo** (modo estrictamente conforme).
- **SAML**: [`samlify`](https://github.com/tngan/samlify) como IdP. Valida contra los
  XSD de SAML y verifica las firmas de nuestro AuthnRequest y nuestros mensajes de
  logout; firma la respuesta, **cifra la aserción** con nuestro certificado de SP y
  cubre el **cierre de sesión único** en ambos sentidos.

No necesita Docker ni Java.

```bash
npm install
```

```bash
npm run interop
```

`node interop.mjs <repo> yes` repite OIDC con los claims dentro del ID token.

## Demo en el navegador

```bash
npm run demo
```

Levanta el API aislado en `:8010` y los dos IdPs con inicio automático como
`dev@acme.com`. Con el Vite del admin (`:5180`) o de studio (`:5184`) abiertos,
escribe ese correo en el login y pulsa «Continuar con SSO»: verás el flujo real
de punta a punta, y «Salir» tras entrar con SAML recorre el cierre de sesión
único. Al arrancar imprime el propietario de prueba, con el que en
Enterprise › SSO se ven las identidades vinculadas y el certificado del SP. Su
base es nueva en cada arranque.

## Lo que encontró (y quedó corregido o documentado)

- Escenia enviaba siempre el secreto en el cuerpo (`client_secret_post`). OIDC usa
  por defecto `client_secret_basic`, y un IdP estricto lo exige: ahora es
  configurable por conexión y el valor por defecto es `basic`.
- Un IdP conforme puede dejar `email` fuera del ID token y entregarlo solo por
  UserInfo: ahora se consulta UserInfo, exigiendo el mismo `sub` (OIDC Core §5.3.2).
- Para SAML, configura el IdP para:
  - **firmar los mensajes de logout**: Escenia los exige, y la metadata SAML no
    puede expresarlo;
  - **cifrar y después firmar** cuando firme la respuesta completa;
  - incluir un **`AuthnStatement`**, que exige el perfil Web Browser SSO.
