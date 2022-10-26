# Colored functions

Coloring is quite a unique concept that allows you to find undesirable architectural patterns in your code.

See the [KPHP documentation about colored functions](https://vkcom.github.io/kphp/kphp-language/howto-by-kphp/colored-functions.html).


## How to test

Execute in console (in a current folder):
```bash
kphp2cpp index.php -M cli
```

Compilation will fail with the message "Potential performance leak".

If you
* either remove all `@kphp-color` from a demo
* or add `@kphp-color slow-ignore` over `Logger::debug()`

then compilation will succeed.
