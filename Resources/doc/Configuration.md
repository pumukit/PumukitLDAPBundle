PumukitLDAPBundle Configuration
===============================

# Add this configuration to use LDAP as SSO on security.yaml
```
security:
    providers:
        my_ldap:
            ldap:
                service: ldap
                base_dn: ou=people,dc=example,dc=es
                search_dn: "uid=uid_example,ou=sistema,dc=example,dc=es"
                search_password: xxxxx
                default_roles: ROLE_USER
                uid_key: uid
        ...
        
    firewalls:
        main:
            pattern: ^/
            context: pumukit
            form_login_ldap:
                login_path: /login
                check_path: /login_check
                service: ldap
                dn_string: 'uid={username},ou=people,dc=example,dc=es'
            logout:       true
            anonymous:    true
        ...
    ...
    
```

If you want to use just LDAP login add this parameter to the main firewall under form_login_ldap:

```
    firewalls:
        main:
            ...
            form_login_ldap:
                success_handler: pumukit_ldap.handler

```


# Default configuration for extension with alias: "pumukit_ldap"
```
pumukit_ldap:
    server: 'ldap://localhost'
    host: localhost
    port: 389
    useSSL: true
    bind_rdn: 'cn=readonly,ou=teachers,dc=exampledomain,dc=es'
    bind_password: 'readonly'
    base_dn: 'ou=teachers,dc=exampledomain,dc=es'
```

* `server` defines the DNS address of the LDAP Server.
* `host` defines the host of the LDAP Server.
* `port` defines the port of the LDAP Server.
* `useSSL` defines if the connection use SSL
* `bind_rdn` defines the DN of the search engine. If not specified, anonymous bind is attempted.
* `bind_password` defines the password of the search engine. If not specified, anonymous bind is attempted.
* `base_dn` defines a user DN of the LDAP Server.
