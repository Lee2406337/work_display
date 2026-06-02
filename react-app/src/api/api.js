import axios from "axios";

const api = axios.create({
  baseURL: "https://b1229011webp2026.infinityfree.me/api",
  withCredentials: true,
});

export default api;