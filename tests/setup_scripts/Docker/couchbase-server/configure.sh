#!/bin/bash

#
# Thanks to Brandt Burnett for this reference
# https://github.com/brantburnett/couchbasefakeit/blob/master/scripts/configure-node.sh
#

set -m

CB_CLI=/opt/couchbase/bin/couchbase-cli
CB_CBQ=/opt/couchbase/bin/cbq

# Start CB Server
/entrypoint.sh couchbase-server &

if [ ! -e "/nodestatus/initialized" ] ; then

  echo "Waiting for the Couchbase Server to Start"

  # Wait a bit for the Server to Start
  sleep 15

  echo "Initializing Couchbase Server"

  # initialize the cluster
  # if using enterprise, add "eventing, analytics" to list of services
  $CB_CLI cluster-init \
    --cluster couchbase://127.0.0.1 \
    --cluster-username admin \
    --cluster-password password \
    --cluster-ramsize 512 \
    --cluster-index-ramsize 512 \
    --cluster-name test \
    --services data,index,query,fts \
    && \
  $CB_CLI bucket-create \
    --cluster couchbase://127.0.0.1 \
    --username admin \
    --password password \
    --bucket test-ing \
    --bucket-type couchbase \
    --bucket-ramsize 256 \
    --bucket-replica 0 \
    --wait \
    && \
  $CB_CLI bucket-create \
    --cluster couchbase://127.0.0.1 \
    --username admin \
    --password password \
    --bucket test-ing2 \
    --bucket-type couchbase \
    --bucket-ramsize 256 \
    --bucket-replica 0 \
    --wait \
    && \
  $CB_CLI user-manage \
    --cluster couchbase://127.0.0.1 \
    --username admin \
    --password password \
    --set \
    --rbac-username dbuser_backend \
    --rbac-password password_backend \
    --rbac-name "DBUser Backend" \
    --roles bucket_full_access[test-ing],bucket_full_access[test-ing2] \
    --auth-domain local

  $CB_CBQ -e http://127.0.0.1:8093 -u admin -p password -q=true -s="CREATE PRIMARY INDEX ON \`test-ing\` USING GSI;"
  $CB_CBQ -e http://127.0.0.1:8093 -u admin -p password -q=true -s="CREATE PRIMARY INDEX ON \`test-ing2\` USING GSI;"

  echo "Couchbase Server initialized."
  echo "Initialized `date +"%D %T"`" > /nodestatus/initialized
else
  echo "Couchbase Server already initialized."
fi

# Wait for CB Server Shutdown
fg 1




