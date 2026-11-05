LDAP configuration
==================

Bundle
------

```yaml
pumukit_ldap:
  server: 'ldaps://ldap.example.org:636'
  bind_rdn: 'cn=readonly,ou=system,dc=example,dc=org'
  bind_password: '%env(LDAP_BIND_PASSWORD)%'
  base_dn: 'ou=people,dc=example,dc=org'
  on_login:
    enable: true
    ldap_attribute: uid
    user_field: username
```

| Option                    | Default    | Description                                                                                   |
|---------------------------|------------|-----------------------------------------------------------------------------------------------|
| `enable`                  | `true`     | If false, nothing connects to LDAP: the button is hidden, the routes give 404, the login lookup is skipped, `isConfigured()` is false for the other bundles and `server` and `base_dn` are not required |
| `server`                  | (required if enabled) | LDAP URI with scheme and port: `ldap://host:389` or `ldaps://host:636`                        |
| `version`                 | `3`        | LDAP protocol version                                                                         |
| `start_tls`               | `false`    | Upgrade a `ldap://` connection with StartTLS                                                  |
| `bind_rdn`, `bind_password` | `null`   | Account used to search. If `bind_rdn` is null, the bind is anonymous                          |
| `base_dn`                 | (required if enabled) | Base DN of the users                                                                          |
| `on_login.enable`         | `false`    | Look up the user in LDAP after every interactive login and dispatch `LdapLoginEvent`         |
| `on_login.ldap_attribute` | `uid`      | LDAP attribute matched against the user                                                       |
| `on_login.user_field`     | `username` | User field matched: `username` or `email`                                                     |

The people search uses the `cn` and `mail` attributes.

"New from LDAP" button
----------------------

The button of the People tab of a multimedia object is rendered by the core when the `pumukit_ldap.ldap`
parameter is true:

```yaml
parameters:
  pumukit_ldap.ldap: true
```

It searches the LDAP people by name or email; the selected person is linked to the multimedia object, and created
from LDAP if there is no person with that email.

Login lookup
------------

With `on_login.enable`, after every interactive login (form, CAS, LMS...) the bundle looks up the user in LDAP and
dispatches `Pumukit\LDAPBundle\Event\LdapLoginEvent` with the user and the LDAP entry. A client listener can
then complete the user with the data that the login does not send:

```php
use Pumukit\LDAPBundle\Event\LdapLoginEvent;
use Pumukit\SchemaBundle\Services\UserService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class CompleteUserFromLdap
{
    public function __construct(private readonly UserService $userService) {}

    #[AsEventListener]
    public function __invoke(LdapLoginEvent $event): void
    {
        $user = $event->getUser();
        $user->setFullname($event->getValue('cn') ?? $user->getFullname());
        $this->userService->update($user, true, false);
    }
}
```

`getValue()` returns the first value of an attribute and `getValues()` all of them; `getEntry()` returns the
whole entry as `ldap_get_entries()` gives it. If LDAP fails or the user is not found the login goes on and the
reason is logged.

Commands
--------

```bash
php bin/console pumukit:ldap:check:connection
php bin/console pumukit:ldap:find:user jdoe
php bin/console pumukit:ldap:find:user jdoe@example.org --attribute=mail
php bin/console pumukit:ldap:find:user jdoe --json
```

`check:connection` connects and binds with the configured account. `find:user` prints the entry that PuMuKIT
receives for a user (the attributes that the bind account can read), with the password attributes hidden. Both
return a non-zero exit code on error.

Other bundles
-------------

`Pumukit\LDAPBundle\Services\LDAPService` can be injected to look up users: `isConfigured()`, `getInfoFrom($attribute,
$value)`, `getInfoFromEmail($email)`, `getListUsers($cn, $mail)`, `searchUsers($term)`, `getName($uid)` and
`getMail($uid)`. The values are escaped.

Logs
----

The bundle logs to its own `ldap` channel, in `%kernel.logs_dir%/ldap-YYYY-MM-DD.log` (`PUMUKIT_LOG_MAX_FILES` files kept, 15 by default): connection
and search errors, users not found on login and people created from LDAP.
