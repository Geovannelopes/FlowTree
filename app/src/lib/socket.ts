// Socket id atual do Echo/Reverb. Módulo isolado para o axios enviar o header
// X-Socket-ID (usado pelo `->toOthers()` do Laravel) sem importar o echo.ts.
let socketId: string | null = null

export function setSocketId(id: string | null): void {
  socketId = id
}

export function getSocketId(): string | null {
  return socketId
}
