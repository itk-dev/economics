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
    faPlus,
} from "@fortawesome/free-solid-svg-icons";

library.add(
    faMaximize,
    faEyeSlash,
    faMinimize,
    faCaretRight,
    faCaretDown,
    faXmark,
    faPlus,
);

// watch(), not i2svg(): i2svg() converts what is in the document at import time
// and nothing after it, so an <i> a Stimulus controller appends later — a
// collection row's remove button, say — stays an empty tag. watch() does that
// first pass and then observes for icons added since.
dom.watch();
