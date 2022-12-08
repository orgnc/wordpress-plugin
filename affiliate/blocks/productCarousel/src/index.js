/* eslint-disable camelcase, no-undef, react/jsx-filename-extension, react/jsx-props-no-spreading */
import { registerBlockType } from '@wordpress/blocks';

import './style.scss';

import OrganicIcon from '../../shared/OrganicIcon';
import Edit from './Edit';
import Save from './Save';

registerBlockType('organic/affiliate-product-carousel', {
  attributes: {
    productCarouselEditURL: {
      type: 'string',
    },
    productCarouselSnippet: {
      type: 'string',
    },
  },
  example: {}, // @todo can you add a picture as an example?
  icon: OrganicIcon,
  edit: (props) => (
    <Edit
      {...props}
      productCarouselCreationURL={organic_affiliate_config_product_carousel.productCarouselCreationURL}
    />
  ),
  save: Save,
});
