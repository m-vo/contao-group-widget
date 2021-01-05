import { WidgetGroup } from "./WidgetGroup";

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll<HTMLElement>('.widget-group').forEach(el => {
        new WidgetGroup(el).init();
    })
})
