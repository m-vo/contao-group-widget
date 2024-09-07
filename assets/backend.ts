import { WidgetGroup } from "./WidgetGroup";

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll<HTMLElement>('.group-widget').forEach(el => {
        new WidgetGroup(el).init();
    })
})
