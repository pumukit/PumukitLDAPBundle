PuMuKIT LDAP Bundle
===================

Bundle based on [Symfony](http://symfony.com/) to work with the [PuMuKIT Video Platform 6.0](https://github.com/pumukit/PuMuKIT/tree/6.0.x) or higher.

The PumukitLDAPBundle connects PuMuKIT to an LDAP server to:

- add people to a multimedia object from the LDAP directory ("New from LDAP" in the People tab),
- look up a user in LDAP after every login, so the client can complete the user with data that the login does
  not send,
- give other bundles (e.g. the LmsBundle) an LDAP lookup service,
- check the connection and the data that the server returns from the console.

Installation
------------

The PHP `ldap` extension is required.

```bash
composer require teltek/pumukit-ldap-bundle
```

If Symfony Flex does not do it, add the bundle to `config/bundles.php`:

```php
Pumukit\LDAPBundle\PumukitLDAPBundle::class => ['all' => true],
```

and its routes:

```yaml
pumukit_ldap:
  resource: "@PumukitLDAPBundle/Resources/config/routing.yml"
```

Then configure the bundle as explained in the [configuration guide](Resources/doc/ConfigurationGuide.md) and clear
the cache:

```bash
php bin/console cache:clear
```

Upgrading from 2.0.x (PuMuKIT 5)
--------------------------------

- The `host`, `port` and `useSSL` options were removed (they were not used): put the scheme and the port in
  `server` (`ldaps://ldap.example.org:636`) and use `start_tls` for StartTLS. `version` is now applied.
- The LDAP login (`form_login_ldap` with the `AuthenticationHandler` success handler and `LDAPUserService`) was
  removed. To complete the users with LDAP data after any login, use the `on_login` option and `LdapLoginEvent`.
- An empty `bind_rdn` binds anonymously, as documented (before, it failed).
- The values sent to LDAP are escaped and the LDAP errors are logged in `var/log/ldap-YYYY-MM-DD.log`.
- The "New from LDAP" button no longer connects to LDAP to be shown; a connection error is shown when searching.
- New `enable` option: if false, the routes give 404 and nothing else of the bundle is loaded (`LDAPService`, login
  listener, commands). New `pumukit:ldap:find:user` command.
