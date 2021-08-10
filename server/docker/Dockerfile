FROM buildpack-deps:stretch

LABEL maintainer="MAINTAINER" value="Gergo Boldogh <info@gexa.hu>"

# Versions of Nginx and nginx-rtmp-module to use
ENV NGINX_VERSION nginx-1.15.0
ENV NGINX_RTMP_MODULE_VERSION 1.2.1

ARG FFMPEG_VERSION=4.2.1

# Install dependencies
RUN apt-get update && \
    apt-get install -y cron dnsutils wget mc htop python3 python3-dev libffi6 libffi-dev libssl-dev yasm curl ca-certificates build-essential procps \
		openssl libpcre3 libpcre3-dev \
		librtmp1 libtheora0 libvorbis-dev libmp3lame0 \
		libvpx4 libx264-dev libx265-dev \
		librtmp-dev libtheora-dev libvpx-dev libfreetype6-dev libmp3lame-dev && \
    rm -rf /var/lib/apt/lists/*

# Download and decompress Nginx
RUN mkdir -p /tmp/build/nginx && \
    cd /tmp/build/nginx && \
    wget -O ${NGINX_VERSION}.tar.gz https://nginx.org/download/${NGINX_VERSION}.tar.gz && \
    tar -zxf ${NGINX_VERSION}.tar.gz

# Download and decompress RTMP module
RUN mkdir -p /tmp/build/nginx-rtmp-module && \
    cd /tmp/build/nginx-rtmp-module && \
    wget -O nginx-rtmp-module-${NGINX_RTMP_MODULE_VERSION}.tar.gz https://github.com/arut/nginx-rtmp-module/archive/v${NGINX_RTMP_MODULE_VERSION}.tar.gz && \
    tar -zxf nginx-rtmp-module-${NGINX_RTMP_MODULE_VERSION}.tar.gz && \
    cd nginx-rtmp-module-${NGINX_RTMP_MODULE_VERSION}

# Build and install Nginx
# The default puts everything under /usr/local/nginx, so it's needed to change
# it explicitly. Not just for order but to have it in the PATH
RUN cd /tmp/build/nginx/${NGINX_VERSION} && \
    ./configure \
        --sbin-path=/usr/local/sbin/nginx \
        --conf-path=/etc/nginx/nginx.conf \
        --error-log-path=/var/log/nginx/error.log \
        --pid-path=/var/run/nginx/nginx.pid \
        --lock-path=/var/lock/nginx/nginx.lock \
        --http-log-path=/var/log/nginx/access.log \
        --http-client-body-temp-path=/tmp/nginx-client-body \
        --with-http_ssl_module \
        --with-threads \
        --with-ipv6 \
        --add-module=/tmp/build/nginx-rtmp-module/nginx-rtmp-module-${NGINX_RTMP_MODULE_VERSION} \
        --with-http_secure_link_module && \
    make -j $(getconf _NPROCESSORS_ONLN) && \
    make install && \
    mkdir /var/lock/nginx && \
    rm -rf /tmp/build

# Forward logs to Docker
RUN ln -sf /dev/stdout /var/log/nginx/access.log && \
    ln -sf /dev/stderr /var/log/nginx/error.log

# Set up config file
COPY conf/nginx-secured-streamkey.conf /etc/nginx/nginx.conf


RUN mkdir /tmp/build
# Download ffmpeg source
RUN cd /tmp/build && \
  wget http://ffmpeg.org/releases/ffmpeg-${FFMPEG_VERSION}.tar.gz && \
  tar -zxf ffmpeg-${FFMPEG_VERSION}.tar.gz && \
  rm ffmpeg-${FFMPEG_VERSION}.tar.gz

# Build ffmpeg
RUN cd /tmp/build/ffmpeg-${FFMPEG_VERSION} && \
  ./configure \
	  --enable-version3 \
	  --enable-gpl \
	  --enable-small \
	  --enable-libx264 \
	  --enable-libx265 \
	  --enable-libvpx \
	  --enable-libtheora \
	  --enable-libvorbis \
	  --enable-librtmp \
	  --enable-postproc \
	  --enable-swresample \
	  --enable-libfreetype \
	  --enable-libmp3lame \
	  --disable-debug \
	  --disable-doc \
	  --disable-ffplay \
	  --extra-libs="-lpthread -lm" && \
	make -j $(getconf _NPROCESSORS_ONLN) && \
	make install

# TODO: Find a better place for this, and replace this with certbot
COPY bash/gen_cert_v2.sh /tmp/generate-certificate.sh

# Setup Google Cloud DNS record for this instance
COPY bash/set_dns.sh /tmp/set_dns.sh
RUN chmod +x /tmp/*.sh

# TODO: Find a better place for SSL files
RUN mkdir -p /var/www/ssl
COPY ssl/* /var/www/ssl/

RUN git clone https://github.com/acmesh-official/acme.sh.git && \
    cd ./acme.sh && \
    ./acme.sh --install --force && \
    alias acme.sh=~/.acme.sh/acme.sh

RUN curl -sL https://deb.nodesource.com/setup_14.x | bash - && \
    apt-get install -y nodejs

RUN npm install http crypto os querystring url -g

RUN mkdir -p /var/www/tokengen

COPY srv/server.js /var/www/tokengen/server.js
COPY srv/package.json /var/www/tokengen/package.json

RUN chmod -R 0755 /var/www/tokengen

RUN cd /var/www/tokengen && npm install

RUN npm install forever -g

# Download gcloud package
RUN curl https://dl.google.com/dl/cloudsdk/release/google-cloud-sdk.tar.gz > /tmp/google-cloud-sdk.tar.gz

# Installing the package
RUN mkdir -p /usr/local/gcloud \
  && tar -C /usr/local/gcloud -xvf /tmp/google-cloud-sdk.tar.gz \
  && /usr/local/gcloud/google-cloud-sdk/install.sh

# Adding the package path to local
ENV PATH $PATH:/usr/local/gcloud/google-cloud-sdk/bin

# TODO: Find a better place for this
ENV GOOGLE_APPLICATION_CREDENTIALS $PATH:/tmp

# TODO: Find a better place for this
COPY adc.json /tmp/adc.json

EXPOSE 80
EXPOSE 443
EXPOSE 8080
EXPOSE 1935

CMD ./tmp/set_dns.sh && \
    forever start /var/www/tokengen/server.js && \
    nginx -g 'daemon off;'