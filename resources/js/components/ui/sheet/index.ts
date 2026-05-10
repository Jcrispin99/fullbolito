import { DialogRoot, DialogTrigger, DialogClose, DialogPortal } from "radix-vue"

export const Sheet = DialogRoot
export const SheetTrigger = DialogTrigger
export const SheetClose = DialogClose
export const SheetPortal = DialogPortal

export const sheetVariants = {
  side: {
    top: "inset-x-0 top-0 border-b",
    bottom: "inset-x-0 bottom-0 border-t",
    left: "inset-y-0 left-0 h-full w-3/4 border-r sm:max-w-sm",
    right: "inset-y-0 right-0 h-full w-3/4 border-l sm:max-w-sm",
  },
} as const

export { default as SheetContent } from "./SheetContent.vue"
export { default as SheetHeader } from "./SheetHeader.vue"
export { default as SheetTitle } from "./SheetTitle.vue"
export { default as SheetDescription } from "./SheetDescription.vue"
export { default as SheetFooter } from "./SheetFooter.vue"

