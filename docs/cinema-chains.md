---
title: Adding a cinema chain
nav_order: 6
---

# Adding a cinema chain

The catalog knows its chains through `config/packages/chains.yaml`, read once by
`CinemaChainRegistry` into `CinemaChain` objects (identifier, name, `CountryCode`,
`\DateTimeZone`, language): a country that does not exist or a typo in a time zone
stops the application at the first use of the registry.

What a second chain (say Cineworld, which spans the United Kingdom and Ireland) needs:

1. **An entry in `app.chains`**, and the same name removed from `app.chains_planned`.
2. **An SDK**, framework-free like `src/Sdk/Pathe`: PSR interfaces only, a `\DateTimeZone` in
   and instants out, a hierarchy of exceptions that tells whether to stop or to skip.
3. **A synchronizer for the chain.** `CatalogSynchronizer::CHAIN` is the only place that says
   "Pathé": extract an interface (`synchronize(CinemaChain $chain, …)`) and let the registry hand
   each chain to the synchronizer that speaks its SDK (tagged services, as for the sign-in
   providers of exercise 9).
4. **Nothing else.** Films, cinemas and cities carry the identifier of their chain; works are common
   to every chain (the planner never offers the same film twice); showtimes are in UTC and shown in
   the time zone of their cinema, which is the chain's unless a cinema says otherwise (a chain can
   span several zones: `Cinema::$timezone` is stored per cinema as a `\DateTimeZone`).
