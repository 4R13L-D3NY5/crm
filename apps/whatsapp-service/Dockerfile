FROM node:20-alpine

WORKDIR /app

RUN apk add --no-cache git python3 make g++

COPY package*.json ./
RUN npm install --omit=dev

COPY . .

RUN mkdir -p /app/sessions

EXPOSE 3000

CMD ["node", "src/index.js"]
