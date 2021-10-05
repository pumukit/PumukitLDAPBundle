PuMuKIT LDAP Bundle
===================

The PumukitLDAPBundle allows connecting to an LDAP Server and to retrieve data from the server for easy metadata filling.

How to install bundle

```bash
composer require teltek/pumukit-ldap-bundle
```

if not, add this to config/bundles.php

```
Pumukit/LDAPBundle/PumukitLDAPBundle::class => ['all' => true]
```

Then execute the following commands

```bash
php bin/console cache:clear
php bin/console cache:clear --env=prod
php bin/console assets:install
```
