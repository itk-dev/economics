/*
// Add fontawesome icons
*/
// Import the stimulus controller
import { Controller } from "@hotwired/stimulus";

// Import the svg core library
import { library } from '@fortawesome/fontawesome-svg-core'

// To keep the package size as small as possible we only import icons we use
// Import the icons from the free solid package.
import {
    faMaximize,
} from '@fortawesome/free-solid-svg-icons'

export default class extends Controller {
    // Add the icons to the library for replacing <i class="fa-solid fa-sort"></i> with the intended svg.
    connect() {
        library.add([
            faMaximize,
        ])
    }
}
