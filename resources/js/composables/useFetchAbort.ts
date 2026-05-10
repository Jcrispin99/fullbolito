/**
 * Creates an abort controller manager for cancelling in-flight fetch requests.
 * When a new request starts, the previous one is automatically cancelled.
 *
 * Usage in stores:
 *   const { getSignal, isAbortError } = createFetchAbort()
 *   await apiClient.get(url, { signal: getSignal() })
 *   catch (err) { if (isAbortError(err)) return }
 */
export function createFetchAbort() {
  let controller: AbortController | null = null

  return {
    /** Aborts the previous request (if any) and returns a new AbortSignal */
    getSignal(): AbortSignal {
      if (controller) controller.abort()
      controller = new AbortController()
      return controller.signal
    },

    /** Returns true if the error was caused by request cancellation */
    isAbortError(err: unknown): boolean {
      return err instanceof Error && err.name === 'CanceledError'
    },
  }
}
