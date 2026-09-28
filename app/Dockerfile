# Frontend Vue 3 + Vite — build estático servido por nginx.
#
# O build acontece na imagem: as VITE_* são embutidas nos assets, então trocar
# uma delas exige rebuild (`docker compose up -d --build app`; o deploy.sh já faz).

# --- build ---
FROM node:22-slim AS build

WORKDIR /app

# Chave pública do Reverb (vem do .env da raiz via docker-compose `build.args`).
ARG VITE_REVERB_APP_KEY=
ENV VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY
# Endereço público do WebSocket. Vazio = mesma origem da página (proxy /app do
# nginx); preenchido = domínio próprio do Reverb (ex.: reverb.flowtree...:443/wss).
ARG VITE_REVERB_HOST=
ENV VITE_REVERB_HOST=$VITE_REVERB_HOST
ARG VITE_REVERB_PORT=
ENV VITE_REVERB_PORT=$VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME=
ENV VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME
# 1 = login pré-preenchido com o admin de demonstração (DEMO_LOGIN no .env da raiz).
ARG VITE_DEMO_LOGIN=0
ENV VITE_DEMO_LOGIN=$VITE_DEMO_LOGIN

# Dependências primeiro (cache de camadas).
COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# --- runtime ---
FROM nginx:1.27-alpine

COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
