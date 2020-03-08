FROM couchbase:community

#
# Thanks to Brandt Burnett for the Reference
# See https://github.com/brantburnett/couchbasefakeit/blob/master/Dockerfile
# Also https://hub.docker.com/r/btburnett3/couchbasefakeit
#

RUN  mkdir /scripts
COPY configure.sh /scripts/configure.sh
RUN chmod +x /scripts/configure.sh

# this is where our initialization indicator file lives
RUN mkdir /nodestatus

WORKDIR /scripts

ENTRYPOINT ["./configure.sh"]
