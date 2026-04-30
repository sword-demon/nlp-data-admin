FROM hyperf/hyperf:8.2-alpine-v3.22-swoole

LABEL maintainer="nlp-data-admin"

WORKDIR /opt/www

EXPOSE 9501

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

CMD ["/entrypoint.sh"]
