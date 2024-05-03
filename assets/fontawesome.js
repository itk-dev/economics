// Import the svg core library
import { library, dom } from '@fortawesome/fontawesome-svg-core'

// Import the icons from the free solid package.
import {
    faMaximize,
    faEyeSlash,
    faMinimize,
} from '@fortawesome/free-solid-svg-icons'

library.add(
    faMaximize,
    faEyeSlash,
    faMinimize,
);
dom.i2svg()
