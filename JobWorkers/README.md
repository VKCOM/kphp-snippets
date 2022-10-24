# Job workers examples

Job workers are separate processes launched at KPHP server startup.

See the [KPHP documentation about job workers](https://vkcom.github.io/kphp/kphp-language/best-practices/parallelism-job-workers.html).


## How to use

1. Compile this folder using KPHP

    ```bash
    kphp2cpp index.php -I .. -M server 
    ```

2. A directory `kphp_out/` should be created, launch a server:

    ```bash
   ./kphp_out/server -f 2 --http-port 8080 --job-workers-ratio 0.5 
    ```

3. Open *http://localhost:8080/* and see an array "calculated inside a job worker".

4. Read the KPHP documentation, copy job classes to your real project, and adapt your root.php file like index.php here to serve job requests.
