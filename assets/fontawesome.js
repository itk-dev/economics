// Import the svg core library
import { library, dom } from "@fortawesome/fontawesome-svg-core";

// Import the icons from the free solid package.
import {
    faMaximize,
    faEyeSlash,
    faMinimize,
    faCaretRight,
    faCaretDown,
    faXmark,
} from "@fortawesome/free-solid-svg-icons";

library.add(
    faMaximize,
    faEyeSlash,
    faMinimize,
    faCaretRight,
    faCaretDown,
    faXmark,
);
dom.i2svg();
