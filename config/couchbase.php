<?php

return [
    // The name of the type field in your model objects
    'type_key' => 'doc_type'

    // The field in the document that holds the tenant ID
    // if you're using multi-tenant features.
    , 'tenant_id_key' => 'tenant_id'

    // The field in the document that always holds the ID value
    // Couchbase does not store the ID in the document by default
    // but we want it in the document so we can treat embedded objects
    // the same as non-embedded objects.
    , 'in_doc_id_key' => 'key_id'
];